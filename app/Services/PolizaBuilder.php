<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankMovement;
use App\Models\InvoiceMatch;
use App\Models\Period;
use Illuminate\Support\Collection;

/**
 * Builds pólizas (double-entry records) from confirmed reconciliation matches.
 *
 * REWORKED for Block A: invoice-level classification is now the source of truth.
 * Each invoice carries cuenta_contable_id (the counterparty subaccount) and
 * cuenta_abono_id (the revenue/expense counterpart), set by the classification
 * flow. This builder READS those instead of guessing accounts from a hardcoded
 * catálogo map. If an invoice isn't classified, it can't produce a póliza — the
 * builder skips it and flags why, so unclassified invoices are caught before
 * filing rather than silently mis-posted.
 *
 * Accounting model (unchanged in shape, now using the assigned accounts):
 *
 *   Ingreso (emitida, paid by deposit):
 *     Debe:  Bancos (movement's account, or 102)   total
 *     Haber: cuenta_abono (revenue 4xx)             subtotal
 *     Haber: IVA trasladado (208)                   iva
 *   The customer subaccount (cuenta_contable, 105.xx) represents the receivable
 *   that the deposit clears; in a direct cobro póliza the bank debit stands in
 *   for it. It is still recorded on the póliza meta for traceability.
 *
 *   Egreso (recibida, paid by charge):
 *     Debe:  cuenta_abono (expense 6xx / cost 5xx)  subtotal
 *     Debe:  IVA acreditable (118)                  iva
 *     Haber: Bancos (movement's account, or 102)    total
 */
class PolizaBuilder
{
    /** @return Collection<int, array> one póliza per confirmed, classified match */
    public function build(Period $period): Collection
    {
        $matches = InvoiceMatch::where('period_id', $period->id)
            ->where('estado', 'confirmado')
            ->with(['movement.account', 'invoice.cuentaContable', 'invoice.cuentaAbono'])
            ->get();

        $catalog = $this->catalogByAgrupador($period->client_id);

        return $matches
            ->map(fn(InvoiceMatch $m) => $this->buildOne($m, $catalog))
            ->filter()      // drop unclassified (null) entries
            ->values();
    }

    /**
     * Invoices in the period that block póliza generation because they lack an
     * abono classification. Surfaced in the UI so they're fixed before filing.
     */
    public function unclassified(Period $period): Collection
    {
        return InvoiceMatch::where('period_id', $period->id)
            ->where('estado', 'confirmado')
            ->with('invoice')
            ->get()
            ->map(fn($m) => $m->invoice)
            ->filter(fn($inv) => $inv && ! $inv->cuenta_abono_id)
            ->values();
    }

    private function buildOne(InvoiceMatch $match, array $catalog): ?array
    {
        $inv = $match->invoice;

        // Authoritative: the invoice must be classified. No abono => no póliza.
        if (! $inv->cuenta_abono_id || ! $inv->cuentaAbono) {
            return null;
        }

        return $inv->tipo === 'emitida'
            ? $this->ingreso($match, $catalog)
            : $this->egreso($match, $catalog);
    }

    private function ingreso(InvoiceMatch $match, array $catalog): array
    {
        $inv = $match->invoice;
        $bank = $this->bankAccount($match->movement, $catalog);
        $iva = $this->iva($inv);
        $base = (float) $inv->subtotal - (float) $inv->descuento;

        $lines = [
            $this->line($bank, 'cargo', (float) $inv->total, 'Cobro ' . $this->counterparty($inv), $inv->uuid),
            $this->line($inv->cuentaAbono, 'abono', $base, 'Ingreso ' . ($inv->serie . $inv->folio), $inv->uuid),
        ];

        if ($iva > 0) {
            $lines[] = $this->line($catalog['208.01'] ?? null, 'abono', $iva, 'IVA trasladado', $inv->uuid);
        }

        return $this->assemble($match, 'Ingreso', $lines);
    }

    private function egreso(InvoiceMatch $match, array $catalog): array
    {
        $inv = $match->invoice;
        $bank = $this->bankAccount($match->movement, $catalog);
        $iva = $this->iva($inv);
        $base = (float) $inv->subtotal - (float) $inv->descuento;

        $lines = [
            $this->line($inv->cuentaAbono, 'cargo', $base, 'Gasto ' . $this->counterparty($inv), $inv->uuid),
        ];

        if ($iva > 0) {
            $lines[] = $this->line($catalog['118.01'] ?? null, 'cargo', $iva, 'IVA acreditable', $inv->uuid);
        }

        $lines[] = $this->line($bank, 'abono', (float) $inv->total, 'Pago ' . ($inv->serie . $inv->folio), $inv->uuid);

        return $this->assemble($match, 'Egreso', $lines);
    }

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
            'cuenta_contable' => $match->invoice->cuentaContable?->numero_cuenta,
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

    private function bankAccount(?BankMovement $mov, array $catalog): ?Account
    {
        return $mov?->account ?? ($catalog['102.01'] ?? null);
    }

    /** @return array<string, Account> keyed by codigo_agrupador */
    private function catalogByAgrupador(int $clientId): array
    {
        return Account::forClient($clientId)->get()->keyBy('codigo_agrupador')->all();
    }

    private function iva($inv): float
    {
        $fromLines = $inv->lines()->sum('iva_trasladado');
        if ($fromLines > 0) {
            return (float) $fromLines;
        }
        $implied = (float) $inv->total - ((float) $inv->subtotal - (float) $inv->descuento);

        return max(0.0, round($implied, 2));
    }

    private function counterparty($inv): string
    {
        return $inv->tipo === 'emitida'
            ? ($inv->receptor_nombre ?: $inv->receptor_rfc)
            : ($inv->emisor_nombre ?: $inv->emisor_rfc);
    }

    private function counterpartyRfc($inv): string
    {
        return $inv->tipo === 'emitida' ? $inv->receptor_rfc : $inv->emisor_rfc;
    }
}
