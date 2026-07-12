<?php

namespace App\Services;

use App\Models\BankMovement;
use App\Models\Invoice;
use App\Models\InvoiceMatch;
use App\Models\Period;

/**
 * Assembles the reporting data that falls out of reconciled data:
 *   - reconciliation summary (what matched, what's outstanding)
 *   - income/expense breakdown (totals, by counterparty, by day)
 *
 * All read-only aggregation over the period's invoices and movements.
 */
class ReportService
{
    /** High-level reconciliation summary for the period. */
    public function reconciliationSummary(Period $period): array
    {
        $invoices = Invoice::where('period_id', $period->id)->where('cancelado', false);
        $movements = BankMovement::whereHas('statement', fn ($q) => $q->where('period_id', $period->id));

        $confirmed = InvoiceMatch::where('period_id', $period->id)->where('estado', 'confirmado')->count();
        $suggested = InvoiceMatch::where('period_id', $period->id)->where('estado', 'sugerido')->count();

        return [
            'facturas_total'     => (clone $invoices)->count(),
            'facturas_emitidas'  => (clone $invoices)->where('tipo', 'emitida')->count(),
            'facturas_recibidas' => (clone $invoices)->where('tipo', 'recibida')->count(),
            'movimientos_total'  => (clone $movements)->count(),
            'enlaces_confirmados'=> $confirmed,
            'enlaces_sugeridos'  => $suggested,
            'sin_factura'        => (clone $movements)->where('estado_conciliacion', 'sin_factura')->count(),
            'sin_movimiento'     => (clone $invoices)->where('estado_conciliacion', 'sin_movimiento')->count(),
            'fuera_periodo'      => (clone $movements)->where('estado_conciliacion', 'fuera_periodo')->count(),
            'monto_emitidas'     => (float) (clone $invoices)->where('tipo', 'emitida')->sum('total'),
            'monto_recibidas'    => (float) (clone $invoices)->where('tipo', 'recibida')->sum('total'),
        ];
    }

    /** Income vs expense breakdown, plus by-counterparty rollups. */
    public function incomeExpense(Period $period): array
    {
        $emitidas = Invoice::where('period_id', $period->id)
            ->where('cancelado', false)->where('tipo', 'emitida')->get();
        $recibidas = Invoice::where('period_id', $period->id)
            ->where('cancelado', false)->where('tipo', 'recibida')->get();

        $ingresos = (float) $emitidas->sum('total');
        $gastos   = (float) $recibidas->sum('total');

        return [
            'ingresos'          => $ingresos,
            'gastos'            => $gastos,
            'balance'           => $ingresos - $gastos,
            'iva_trasladado'    => (float) $emitidas->sum(fn ($i) => $i->lines()->sum('iva_trasladado')),
            'iva_acreditable'   => (float) $recibidas->sum(fn ($i) => $i->lines()->sum('iva_trasladado')),
            'por_cliente'       => $this->byCounterparty($emitidas, 'receptor'),
            'por_proveedor'     => $this->byCounterparty($recibidas, 'emisor'),
        ];
    }

    /** Group invoices by counterparty, sorted by amount descending. */
    private function byCounterparty($invoices, string $side): array
    {
        $rfcField    = "{$side}_rfc";
        $nombreField = "{$side}_nombre";

        return $invoices->groupBy($rfcField)
            ->map(fn ($group) => [
                'rfc'    => $group->first()->{$rfcField},
                'nombre' => $group->first()->{$nombreField} ?: $group->first()->{$rfcField},
                'count'  => $group->count(),
                'total'  => round((float) $group->sum('total'), 2),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Daily cashflow series for the period chart: charges and deposits per day.
     * Returns arrays aligned to a sorted list of dates.
     */
    public function dailyCashflow(Period $period): array
    {
        $movements = BankMovement::whereHas('statement', fn ($q) => $q->where('period_id', $period->id))
            ->orderBy('fecha')
            ->get(['fecha', 'cargo', 'deposito']);

        $byDay = [];
        foreach ($movements as $m) {
            $key = $m->fecha?->format('Y-m-d');
            if (! $key) {
                continue;
            }
            $byDay[$key]['cargo']    = ($byDay[$key]['cargo'] ?? 0) + (float) $m->cargo;
            $byDay[$key]['deposito'] = ($byDay[$key]['deposito'] ?? 0) + (float) $m->deposito;
        }

        ksort($byDay);

        return [
            'labels'    => array_keys($byDay),
            'cargos'    => array_map(fn ($d) => round($d['cargo'] ?? 0, 2), array_values($byDay)),
            'depositos' => array_map(fn ($d) => round($d['deposito'] ?? 0, 2), array_values($byDay)),
        ];
    }
}
