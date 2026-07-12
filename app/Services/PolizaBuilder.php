<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankMovement;
use App\Models\Invoice;
use App\Models\InvoiceMatch;
use App\Models\Period;
use Illuminate\Support\Collection;

/**
 * Builds pólizas (double-entry accounting records) from confirmed reconciliation
 * matches. Each póliza balances: total debits (cargo) == total credits (abono).
 *
 * This is the conceptual bridge to Phase 5: a póliza line carries the CFDI UUID,
 * so the same records serialize directly into contabilidad electrónica later.
 *
 * The accounting model per match (simplified, income/expense flow):
 *
 *   Ingreso (emitida invoice, paid by a deposit):
 *     Debe:  Bancos (102)              total
 *     Haber: Ingresos (401)            subtotal
 *     Haber: IVA trasladado (208)      iva
 *
 *   Egreso (recibida invoice, paid by a charge):
 *     Debe:  Gastos/Proveedor (601/201) subtotal
 *     Debe:  IVA acreditable (118)       iva
 *     Haber: Bancos (102)               total
 *
 * The bank account comes from the movement's assigned account (learned defaults);
 * the counterpart accounts come from the client's catálogo by CodAgrupador, with
 * safe fallbacks. Nothing here is persisted — it returns structured pólizas for
 * the report and, later, the XML.
 */
class PolizaBuilder
{
    /**
     * @return Collection<int, array> one póliza per confirmed match
     */
    public function build(Period $period): Collection
    {
        $matches = InvoiceMatch::where('period_id', $period->id)
            ->where('estado', 'confirmado')
            ->with(['movement.account', 'invoice'])
            ->get();

        $catalog = $this->catalogByAgrupador($period->client_id);

        return $matches->map(function (InvoiceMatch $match) use ($catalog) {
            return $match->invoice->tipo === 'emitida'
                ? $this->ingresoPoliza($match, $catalog)
                : $this->egresoPoliza($match, $catalog);
        })->values();
    }

    /** Income póliza: money in, revenue + IVA trasladado recognized. */
    private function ingresoPoliza(InvoiceMatch $match, array $catalog): array
    {
        $inv = $match->invoice;
        $mov = $match->movement;

        $bankAccount = $this->bankAccountFor($mov, $catalog);
        $iva = (float) $this->invoiceIvaTrasladado($inv);
        $base = (float) $inv->subtotal - (float) $inv->descuento;

        $lines = [
            $this->line($bankAccount, 'cargo', (float) $inv->total, 'Cobro ' . $this->counterparty($inv), $inv->uuid),
            $this->line($catalog['401.01'] ?? null, 'abono', $base, 'Ingreso ' . ($inv->serie . $inv->folio), $inv->uuid),
        ];

        if ($iva > 0) {
            $lines[] = $this->line($catalog['208.01'] ?? null, 'abono', $iva, 'IVA trasladado', $inv->uuid);
        }

        return $this->assemble($match, 'Ingreso', $lines);
    }

    /** Expense póliza: money out, expense + IVA acreditable recognized. */
    private function egresoPoliza(InvoiceMatch $match, array $catalog): array
    {
        $inv = $match->invoice;
        $mov = $match->movement;

        $bankAccount = $this->bankAccountFor($mov, $catalog);
        $iva = (float) $this->invoiceIvaTrasladado($inv);
        $base = (float) $inv->subtotal - (float) $inv->descuento;

        $lines = [
            $this->line($catalog['601.01'] ?? null, 'cargo', $base, 'Gasto ' . $this->counterparty($inv), $inv->uuid),
        ];

        if ($iva > 0) {
            $lines[] = $this->line($catalog['118.01'] ?? null, 'cargo', $iva, 'IVA acreditable', $inv->uuid);
        }

        $lines[] = $this->line($bankAccount, 'abono', (float) $inv->total, 'Pago ' . ($inv->serie . $inv->folio), $inv->uuid);

        return $this->assemble($match, 'Egreso', $lines);
    }

    /** Assemble a póliza and verify it balances. */
    private function assemble(InvoiceMatch $match, string $tipo, array $lines): array
    {
        $lines = array_values(array_filter($lines));

        $totalCargo = array_sum(array_column($lines, 'cargo'));
        $totalAbono = array_sum(array_column($lines, 'abono'));

        return [
            'match_id'    => $match->id,
            'tipo'        => $tipo,
            'fecha'       => $match->movement?->fecha?->format('Y-m-d'),
            'concepto'    => $tipo . ' — ' . $this->counterparty($match->invoice),
            'uuid'        => $match->invoice->uuid,
            'rfc'         => $this->counterpartyRfc($match->invoice),
            'monto_total' => (float) $match->invoice->total,
            'lines'       => $lines,
            'total_cargo' => round($totalCargo, 2),
            'total_abono' => round($totalAbono, 2),
            'cuadra'      => abs($totalCargo - $totalAbono) < 0.01,
        ];
    }

    private function line(?Account $account, string $side, float $amount, string $concepto, string $uuid): ?array
    {
        if ($amount <= 0) {
            return null;
        }

        return [
            'account_id'    => $account?->id,
            'numero_cuenta' => $account?->numero_cuenta ?? '(sin cuenta)',
            'nombre_cuenta' => $account?->nombre ?? '(cuenta no asignada)',
            'cod_agrupador' => $account?->codigo_agrupador,
            'concepto'      => $concepto,
            'uuid'          => $uuid,
            'cargo'         => $side === 'cargo' ? round($amount, 2) : 0.0,
            'abono'         => $side === 'abono' ? round($amount, 2) : 0.0,
        ];
    }

    /**
     * Bank account for a movement: prefer the account the accountant assigned;
     * fall back to the catálogo's default bank account (102.01).
     */
    private function bankAccountFor(?BankMovement $mov, array $catalog): ?Account
    {
        if ($mov?->account) {
            return $mov->account;
        }

        return $catalog['102.01'] ?? null;
    }

    /** @return array<string, Account> keyed by codigo_agrupador */
    private function catalogByAgrupador(int $clientId): array
    {
        return Account::where('client_id', $clientId)
            ->get()
            ->keyBy('codigo_agrupador')
            ->all();
    }

    private function invoiceIvaTrasladado(Invoice $inv): float
    {
        // Sum line-level IVA trasladado; falls back to total - subtotal if lines empty.
        $fromLines = $inv->lines()->sum('iva_trasladado');
        if ($fromLines > 0) {
            return (float) $fromLines;
        }

        $implied = (float) $inv->total - ((float) $inv->subtotal - (float) $inv->descuento);

        return max(0.0, round($implied, 2));
    }

    private function counterparty(Invoice $inv): string
    {
        return $inv->tipo === 'emitida'
            ? ($inv->receptor_nombre ?: $inv->receptor_rfc)
            : ($inv->emisor_nombre ?: $inv->emisor_rfc);
    }

    private function counterpartyRfc(Invoice $inv): string
    {
        return $inv->tipo === 'emitida'
            ? $inv->receptor_rfc
            : $inv->emisor_rfc;
    }
}
