<?php

namespace App\Http\Controllers;

use App\Models\Poliza;
use App\Services\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Asientos contables — a read-only summary of the pólizas generated for the
 * active period (provisión / cobro / provisión de gasto / pago de gasto). One
 * row per póliza, with a tipo filter, a totals footer, and a row-detail endpoint
 * that returns the póliza's cargo/abono lines.
 */
class PolizaSummaryController extends Controller
{
    /** Tipos shown, in display order (label from Poliza::tipoLabel). */
    private const TIPOS = ['provision', 'cobro', 'provision_gasto', 'pago_gasto'];

    public function __construct(
        private readonly WorkContext $context,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->context->hasPeriod()) {
            return redirect()->route('dashboard')
                ->with('toast', ['type' => 'warning', 'message' => 'Selecciona un cliente y periodo primero.']);
        }

        $period = $this->context->period();
        $tipo   = $request->string('tipo')->toString();

        $query = Poliza::where('period_id', $period->id)
            ->when(in_array($tipo, self::TIPOS, true), fn ($q) => $q->where('tipo', $tipo))
            ->with('invoice:id,serie,folio,receptor_nombre,receptor_rfc,emisor_nombre,emisor_rfc,tipo')
            ->orderBy('fecha')
            ->orderBy('id');

        $polizas = $query->paginate(50)->withQueryString();

        // Totals across the whole filtered set (not just the page).
        $totalsQuery = Poliza::where('period_id', $period->id)
            ->when(in_array($tipo, self::TIPOS, true), fn ($q) => $q->where('tipo', $tipo));

        $totals = [
            'count' => (clone $totalsQuery)->count(),
            'cargo' => (float) (clone $totalsQuery)->sum('total_cargo'),
            'abono' => (float) (clone $totalsQuery)->sum('total_abono'),
        ];

        // Label map for the filter dropdown.
        $tipoLabels = [];
        foreach (self::TIPOS as $t) {
            $tipoLabels[$t] = (new Poliza(['tipo' => $t]))->tipoLabel();
        }

        return view('polizas.summary', [
            'period'     => $period,
            'polizas'    => $polizas,
            'totals'     => $totals,
            'tipo'       => in_array($tipo, self::TIPOS, true) ? $tipo : '',
            'tipoLabels' => $tipoLabels,
        ]);
    }

    /** Row detail: the póliza's cargo/abono lines (the asiento itself). */
    public function show(Poliza $poliza): JsonResponse
    {
        // Guard: only pólizas of the active client/period are viewable here.
        abort_unless(
            $this->context->hasPeriod()
            && $poliza->period_id === $this->context->period()->id,
            404
        );

        $poliza->load('lines:id,poliza_id,numero_cuenta,nombre_cuenta,concepto,cargo,abono');

        return response()->json([
            'num_iden' => $poliza->num_iden,
            'tipo'     => $poliza->tipoLabel(),
            'fecha'    => $poliza->fecha?->format('Y-m-d'),
            'concepto' => $poliza->concepto,
            'cuadra'   => $poliza->cuadra,
            'lines'    => $poliza->lines->map(fn ($l) => [
                'numero_cuenta' => $l->numero_cuenta,
                'nombre_cuenta' => $l->nombre_cuenta,
                'concepto'      => $l->concepto,
                'cargo'         => (float) $l->cargo,
                'abono'         => (float) $l->abono,
            ]),
            'total_cargo' => (float) $poliza->total_cargo,
            'total_abono' => (float) $poliza->total_abono,
        ]);
    }
}
