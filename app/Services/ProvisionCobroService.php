<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankMovement;
use App\Models\Invoice;
use App\Models\Poliza;
use App\Models\PolizaLine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Builds the two income pólizas your client's cuadros describe.
 *
 *   PROVISIÓN (accrual, on invoice issue) — "botón provisión":
 *     Debe   105.01.#  (cliente — receivable)         total
 *     Haber  4xx       (ingreso, per concepto)        subtotal − descuento
 *     Haber  209.01    (IVA trasladado NO cobrado)    iva
 *
 *   COBRO (cash, on payment) — "botón fecha de pago":
 *     Debe   102.01    (bancos)                        total
 *     Haber  105.01.#  (cliente — receivable)          total
 *     Debe   209.01    (IVA trasladado NO cobrado)     iva   ← reverses provisión
 *     Haber  208.01    (IVA trasladado cobrado)        iva   ← now collected
 *
 * IVA sobre flujo (cash-basis IVA): at issue the IVA is accrued but not yet
 * collected, so it sits in 209.01. Collection reclassifies it 209.01 → 208.01.
 * Both cuadros balance on their own.
 *
 * The receivable (the client's own 105.01.# from D1, or the counterparty's
 * subaccount) is the hinge: provisión creates it, cobro clears it.
 *
 * Both are persisted. Generating provisión twice for the same invoice is
 * refused; cobro likewise. They're independent — you can provision now and cobrar
 * days later when payment lands.
 */
class ProvisionCobroService
{
    private const IVA_TRASLADADO   = '208.01';   // IVA trasladado cobrado
    private const IVA_NO_COBRADO   = '209.01';   // IVA trasladado no cobrado (devengado)
    private const BANCOS           = '102.01';

    public function __construct(
        private readonly CounterpartyAccountService $counterparty,
    ) {}

    /**
     * Generate the póliza de provisión for an income invoice.
     *
     * @param array<int,int> $conceptAccounts optional map of line index => account_id,
     *   letting the UI pick a revenue account per concepto (the cuadro's
     *   "seleccionar cuenta contable de ingreso"). Falls back to the invoice's
     *   cuenta_abono when not provided.
     */
    public function generateProvision(Invoice $invoice, array $conceptAccounts = []): Poliza
    {
        if ($invoice->tipo !== 'emitida') {
            throw new RuntimeException('La provisión de ingreso aplica solo a facturas emitidas.');
        }

        if ($this->existing($invoice, 'provision')) {
            throw new RuntimeException('Esta factura ya tiene póliza de provisión.');
        }

        $receivable = $this->receivableFor($invoice);
        $catalog = $this->catalog($invoice->client_id);

        $base = (float) $invoice->subtotal - (float) $invoice->descuento;
        $iva = (float) ($invoice->iva_trasladado ?: $invoice->lines->sum('iva_trasladado'));

        return DB::transaction(function () use ($invoice, $receivable, $catalog, $conceptAccounts, $base, $iva) {
            $poliza = Poliza::create([
                'client_id'  => $invoice->client_id,
                'period_id'  => $invoice->period_id,
                'invoice_id' => $invoice->id,
                'tipo'       => 'provision',
                'num_iden'   => 'Prov' . $invoice->id,
                'fecha'      => $invoice->fecha_emision?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'concepto'   => 'Provisión ingreso — ' . $this->counterpartyName($invoice),
            ]);

            $lines = [];

            // Debe: receivable, the full total.
            $lines[] = $this->line(
                $receivable,
                'cargo',
                (float) $invoice->total,
                'Cliente ' . $this->counterpartyName($invoice),
                $invoice->uuid
            );

            // Haber: revenue. Either split per concepto (if the UI chose accounts),
            // or a single line to the invoice's abono account.
            if (! empty($conceptAccounts)) {
                foreach ($invoice->lines as $i => $line) {
                    $accId = $conceptAccounts[$i] ?? $invoice->cuenta_abono_id;
                    $acc = $accId ? Account::find($accId) : null;
                    $lineBase = (float) $line->importe - (float) ($line->descuento ?? 0);
                    if ($lineBase > 0) {
                        $lines[] = $this->line($acc, 'abono', $lineBase, $line->descripcion, $invoice->uuid);
                    }
                }
            } else {
                $revenue = $invoice->cuentaAbono;
                if (! $revenue) {
                    throw new RuntimeException('La factura no tiene cuenta de ingreso (abono) asignada. Clasifícala primero.');
                }
                $lines[] = $this->line($revenue, 'abono', $base, 'Ingreso ' . ($invoice->serie . $invoice->folio), $invoice->uuid);
            }

            // Haber: IVA trasladado NO cobrado. On issue the IVA is accrued but not
            // yet collected (IVA sobre flujo), so it lands in 209.01. The cobro
            // póliza reclassifies it to 208.01 when payment arrives.
            if ($iva > 0) {
                $lines[] = $this->line($catalog[self::IVA_NO_COBRADO] ?? null, 'abono', $iva, 'IVA trasladado no cobrado', $invoice->uuid);
            }

            $this->persistLines($poliza, $lines);

            return $poliza->fresh('lines');
        });
    }

    /**
     * Generate the póliza de cobro. The payment source (manual date, complemento,
     * estado de cuenta) is D4; here we accept a date and optional source refs and
     * build the cash entry.
     */
    public function generateCobro(
        Invoice $invoice,
        string $fechaPago,
        string $origen = 'manual',
        ?int $bankMovementId = null,
        ?string $paymentUuid = null,
    ): Poliza {
        if ($invoice->tipo !== 'emitida') {
            throw new RuntimeException('El cobro de ingreso aplica solo a facturas emitidas.');
        }

        if (! $this->existing($invoice, 'provision')) {
            throw new RuntimeException('Genera primero la póliza de provisión.');
        }

        if ($this->existing($invoice, 'cobro')) {
            throw new RuntimeException('Esta factura ya tiene póliza de cobro.');
        }

        $receivable = $this->receivableFor($invoice);
        $catalog = $this->catalog($invoice->client_id);
        $bank = $bankMovementId
            ? (BankMovement::find($bankMovementId)?->account ?? ($catalog[self::BANCOS] ?? null))
            : ($catalog[self::BANCOS] ?? null);

        // Same IVA base the provisión used, to reclassify 209.01 → 208.01.
        $iva = (float) ($invoice->iva_trasladado ?: $invoice->lines->sum('iva_trasladado'));

        return DB::transaction(function () use ($invoice, $receivable, $bank, $catalog, $iva, $fechaPago, $origen, $bankMovementId, $paymentUuid) {
            $poliza = Poliza::create([
                'client_id'        => $invoice->client_id,
                'period_id'        => $invoice->period_id,
                'invoice_id'       => $invoice->id,
                'tipo'             => 'cobro',
                'num_iden'         => 'Cobro' . $invoice->id,
                'fecha'            => $fechaPago,
                'concepto'         => 'Cobro ingreso — ' . $this->counterpartyName($invoice),
                'origen_pago'      => $origen,
                'bank_movement_id' => $bankMovementId,
                'payment_uuid'     => $paymentUuid,
            ]);

            $lines = [
                $this->line($bank, 'cargo', (float) $invoice->total, 'Cobro ' . ($invoice->serie . $invoice->folio), $invoice->uuid),
                $this->line($receivable, 'abono', (float) $invoice->total, 'Cliente ' . $this->counterpartyName($invoice), $invoice->uuid),
            ];

            // IVA reclassification: what was accrued in 209.01 at provisión is now
            // collected, so it moves to 208.01. Debe 209.01 (reverse) / Haber 208.01.
            if ($iva > 0) {
                $lines[] = $this->line($catalog[self::IVA_NO_COBRADO] ?? null, 'cargo', $iva, 'IVA trasladado no cobrado', $invoice->uuid);
                $lines[] = $this->line($catalog[self::IVA_TRASLADADO] ?? null, 'abono', $iva, 'IVA trasladado cobrado', $invoice->uuid);
            }

            $this->persistLines($poliza, $lines);

            return $poliza->fresh('lines');
        });
    }

    public function existing(Invoice $invoice, string $tipo): ?Poliza
    {
        return Poliza::where('invoice_id', $invoice->id)->where('tipo', $tipo)->first();
    }

    /** The receivable subaccount for this invoice's customer (mints if needed). */
    private function receivableFor(Invoice $invoice): Account
    {
        return $this->counterparty->resolve(
            $invoice->client,
            $invoice->receptor_rfc,
            $invoice->receptor_nombre ?? '',
            '105.01',
            '105.02',
        );
    }

    /** @return array<string, Account> keyed by codigo_agrupador */
    private function catalog(int $clientId): array
    {
        return Account::forClient($clientId)->get()->keyBy('codigo_agrupador')->all();
    }

    /** @param array<int,?array> $lines */
    private function persistLines(Poliza $poliza, array $lines): void
    {
        $lines = array_values(array_filter($lines));
        $cargo = 0.0;
        $abono = 0.0;

        foreach ($lines as $l) {
            PolizaLine::create(['poliza_id' => $poliza->id] + $l);
            $cargo += $l['cargo'];
            $abono += $l['abono'];
        }

        $poliza->update([
            'total_cargo' => round($cargo, 2),
            'total_abono' => round($abono, 2),
            'cuadra'      => abs($cargo - $abono) < 0.01,
        ]);
    }

    /** @return array<string,mixed>|null */
    private function line(?Account $account, string $side, float $amount, string $concepto, ?string $uuid): ?array
    {
        if ($amount <= 0) {
            return null;
        }

        return [
            'account_id'    => $account?->id,
            'numero_cuenta' => $account?->numero_cuenta ?? '(sin cuenta)',
            'nombre_cuenta' => $account?->nombre,
            'concepto'      => $concepto,
            'uuid'          => $uuid,
            'cargo'         => $side === 'cargo' ? round($amount, 2) : 0.0,
            'abono'         => $side === 'abono' ? round($amount, 2) : 0.0,
        ];
    }

    private function counterpartyName(Invoice $invoice): string
    {
        return $invoice->receptor_nombre ?: $invoice->receptor_rfc;
    }
}
