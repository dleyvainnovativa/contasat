<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\BankMovement;
use App\Models\Invoice;
use App\Models\InvoiceMatch;
use App\Services\AccountAssignmentService;
use App\Services\ReconciliationEngine;
use App\Services\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
        private readonly ReconciliationEngine $engine,
        private readonly AccountAssignmentService $accounts,
    ) {}

    /** The review screen: matches to confirm, plus the exception buckets. */
    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->context->hasPeriod()) {
            return redirect()
                ->route('dashboard')
                ->with('toast', ['type' => 'warning', 'message' => 'Selecciona un cliente y periodo primero.']);
        }

        $period = $this->context->period();

        $matches = InvoiceMatch::where('period_id', $period->id)
            ->with(['movement', 'invoice'])
            ->orderByRaw("FIELD(estado, 'sugerido', 'confirmado', 'rechazado')")
            ->orderByDesc('score')
            ->get();

        // Exception buckets — the digital "missing lines" tabs.
        $sinFactura = BankMovement::whereHas('statement', fn ($q) => $q->where('period_id', $period->id))
            ->where('estado_conciliacion', 'sin_factura')
            ->orderBy('fecha')
            ->get();

        $sinMovimiento = Invoice::where('period_id', $period->id)
            ->where('estado_conciliacion', 'sin_movimiento')
            ->orderBy('fecha_emision')
            ->get();

        $fueraPeriodo = BankMovement::whereHas('statement', fn ($q) => $q->where('period_id', $period->id))
            ->where('estado_conciliacion', 'fuera_periodo')
            ->orderBy('fecha')
            ->get();

        $accounts = Account::where('client_id', $period->client_id)
            ->where('activo', true)
            ->where('es_afectable', true)
            ->orderBy('numero_cuenta')
            ->get();

        // Pre-compute suggested accounts for confirmed matches missing one.
        $accountSuggestions = [];
        foreach ($matches->where('estado', 'confirmado') as $m) {
            if ($m->movement && ! $m->movement->account_id) {
                $accountSuggestions[$m->movement->id] = $this->accounts->suggestFor($m->movement);
            }
        }

        return view('reconciliation.index', [
            'period'             => $period,
            'matches'            => $matches,
            'sinFactura'         => $sinFactura,
            'sinMovimiento'      => $sinMovimiento,
            'fueraPeriodo'       => $fueraPeriodo,
            'accounts'           => $accounts,
            'accountSuggestions' => $accountSuggestions,
            'counts'             => [
                'sugeridos'  => $matches->where('estado', 'sugerido')->count(),
                'confirmados'=> $matches->where('estado', 'confirmado')->count(),
                'sinFactura' => $sinFactura->count(),
                'sinMovimiento' => $sinMovimiento->count(),
                'fueraPeriodo'  => $fueraPeriodo->count(),
            ],
        ]);
    }

    /** Run (or re-run) the deterministic matcher for the active period. */
    public function run(): JsonResponse
    {
        if (! $this->context->hasPeriod()) {
            return response()->json(['message' => 'Selecciona un cliente y periodo primero.'], 422);
        }

        $summary = $this->engine->reconcile($this->context->period());

        return response()->json([
            'ok'      => true,
            'summary' => $summary,
            'message' => "Conciliación: {$summary['matched']} enlazados, "
                . ($summary['sin_factura'] + $summary['sin_movimiento']) . ' por revisar.',
        ]);
    }

    public function confirm(InvoiceMatch $match): JsonResponse
    {
        $match->update([
            'estado'       => 'confirmado',
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ]);

        // Auto-apply a learned account default if we have one.
        $suggested = $this->accounts->suggestFor($match->movement);
        if ($suggested) {
            $this->accounts->assign($match->movement, $suggested);
        }

        return response()->json(['ok' => true, 'suggested_account' => $suggested]);
    }

    public function reject(InvoiceMatch $match): JsonResponse
    {
        $movementId = $match->bank_movement_id;
        $invoiceId  = $match->invoice_id;

        $match->delete();

        // Return both sides to the unmatched pool.
        BankMovement::where('id', $movementId)->update(['estado_conciliacion' => 'sin_factura']);
        Invoice::where('id', $invoiceId)->update(['estado_conciliacion' => 'sin_movimiento']);

        return response()->json(['ok' => true]);
    }

    /** Manually link a movement to an invoice (from the exception buckets). */
    public function link(Request $request): JsonResponse
    {
        $data = $request->validate([
            'movement_id' => ['required', 'exists:bank_movements,id'],
            'invoice_id'  => ['required', 'exists:invoices,id'],
        ]);

        $period = $this->context->period();

        $match = InvoiceMatch::updateOrCreate(
            ['bank_movement_id' => $data['movement_id'], 'invoice_id' => $data['invoice_id']],
            [
                'client_id'    => $period->client_id,
                'period_id'    => $period->id,
                'metodo'       => 'manual',
                'estado'       => 'confirmado',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
            ]
        );

        BankMovement::where('id', $data['movement_id'])->update(['estado_conciliacion' => 'conciliado']);
        Invoice::where('id', $data['invoice_id'])->update(['estado_conciliacion' => 'conciliado']);

        return response()->json(['ok' => true, 'match_id' => $match->id]);
    }

    /** Assign an account to a movement (and reinforce the learned default). */
    public function assignAccount(Request $request, BankMovement $movement): JsonResponse
    {
        $data = $request->validate([
            'account_id' => ['required', 'exists:accounts,id'],
        ]);

        $this->accounts->assign($movement, (int) $data['account_id']);

        return response()->json(['ok' => true]);
    }

    /**
     * The "questions for client" list — movements with no invoice and invoices
     * with no movement, formatted for the accountant to send to the client. This
     * is the thing he currently builds by hand in a separate Excel tab.
     */
    public function questions(): View|RedirectResponse
    {
        if (! $this->context->hasPeriod()) {
            return redirect()->route('dashboard');
        }

        $period = $this->context->period();

        $sinFactura = BankMovement::whereHas('statement', fn ($q) => $q->where('period_id', $period->id))
            ->where('estado_conciliacion', 'sin_factura')
            ->orderBy('fecha')
            ->get();

        $sinMovimiento = Invoice::where('period_id', $period->id)
            ->where('estado_conciliacion', 'sin_movimiento')
            ->orderBy('fecha_emision')
            ->get();

        return view('reconciliation.questions', [
            'period'        => $period,
            'sinFactura'    => $sinFactura,
            'sinMovimiento' => $sinMovimiento,
        ]);
    }
}
