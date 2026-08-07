<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\WorkContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * The Facturas submenu (Block B) — five filtered views over the same invoice
 * data, keyed on CFDI tipo_comprobante and direction:
 *
 *   ingreso   -> tipo_comprobante I, emitida  (client's income)
 *   gasto     -> tipo_comprobante I, recibida (client's expense)
 *   nomina    -> tipo_comprobante N
 *   pago_emitido   -> tipo_comprobante P, emitida
 *   pago_recibido  -> tipo_comprobante P, recibida
 *
 * These are slices, not new storage — everything filters the invoices table the
 * Phase 1 ingest already populates. Tax totals (iva_trasladado, iva_retenido,
 * isr_retenido) are denormalized onto the invoice at ingest, so list queries read
 * them directly rather than aggregating lines on every request.
 */
class InvoiceViewController extends Controller
{
    /** view key => [label, tipo_comprobante, direction|null] */
    private const VIEWS = [
        'ingreso'       => ['Provisión facturas de ingreso', 'I', 'emitida'],
        'gasto'         => ['Provisión facturas de gastos',  'I', 'recibida'],
        'nomina'        => ['Provisión facturas de nómina',  'N', null],
        'pago_emitido'  => ['Complementos de pago emitidos', 'P', 'emitida'],
        'pago_recibido' => ['Complementos de pago recibidos', 'P', 'recibida'],
    ];

    public function __construct(
        private readonly WorkContext $context,
    ) {}

    public function show(Request $request, string $view): View|RedirectResponse
    {
        if (! isset(self::VIEWS[$view])) {
            abort(404);
        }

        if (! $this->context->hasPeriod()) {
            return redirect()->route('dashboard')
                ->with('toast', ['type' => 'warning', 'message' => 'Selecciona un cliente y periodo primero.']);
        }

        [$label, $tipoComprobante, $direction] = self::VIEWS[$view];
        $period = $this->context->period();

        $query = Invoice::where('period_id', $period->id)
            ->where('tipo_comprobante', $tipoComprobante)
            ->when($direction, fn($q) => $q->where('tipo', $direction))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = $request->string('q')->toString();
                $q->where(fn($s) => $s->where('folio', 'like', "%{$term}%")
                    ->orWhere('uuid', 'like', "%{$term}%")
                    ->orWhere('receptor_nombre', 'like', "%{$term}%")
                    ->orWhere('emisor_nombre', 'like', "%{$term}%")
                    ->orWhere('receptor_rfc', 'like', "%{$term}%")
                    ->orWhere('emisor_rfc', 'like', "%{$term}%"));
            })
            ->with(['cuentaContable', 'cuentaAbono'])
            ->orderByDesc('fecha_emision');

        $invoices = $query->paginate(25)->withQueryString();

        // Totals for the filtered set (whole period, not just the page).
        $totalsQuery = Invoice::where('period_id', $period->id)
            ->where('tipo_comprobante', $tipoComprobante)
            ->when($direction, fn($q) => $q->where('tipo', $direction));

        $totals = [
            'count'    => (clone $totalsQuery)->count(),
            'subtotal' => (clone $totalsQuery)->sum('subtotal'),
            'iva'      => (clone $totalsQuery)->sum('iva_trasladado'),
            'total'    => (clone $totalsQuery)->sum('total'),
        ];

        return view('invoices.filtered', [
            'view'      => $view,
            'label'     => $label,
            'isPago'    => $tipoComprobante === 'P',
            'isNomina'  => $tipoComprobante === 'N',
            'invoices'  => $invoices,
            'totals'    => $totals,
            'period'    => $period,
            'q'         => $request->string('q')->toString(),
            'navItems'  => $this->navSummary($period->id),
        ]);
    }

    /**
     * Per-view counts for the submenu, so each tab can show how many invoices it
     * holds. One grouped query rather than five counts.
     *
     * @return array<string, int>
     */
    private function navSummary(int $periodId): array
    {
        $rows = Invoice::where('period_id', $periodId)
            ->selectRaw('tipo_comprobante, tipo, count(*) as n')
            ->groupBy('tipo_comprobante', 'tipo')
            ->get();

        $counts = [];
        foreach (self::VIEWS as $key => [$label, $tc, $dir]) {
            $counts[$key] = $rows
                ->where('tipo_comprobante', $tc)
                ->when($dir, fn($c) => $c->where('tipo', $dir))
                ->sum('n');
        }

        return $counts;
    }
}
