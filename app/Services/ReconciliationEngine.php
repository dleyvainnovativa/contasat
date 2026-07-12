<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Models\BankMovement;
use App\Models\Invoice;
use App\Models\InvoiceMatch;
use App\Models\Period;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates reconciliation for a period. Runs the deterministic matcher,
 * persists proposed links as "sugerido" matches, and buckets everything so the
 * review UI can present the exceptions — the digital replacement for the
 * "missing lines" Excel tab.
 *
 * Buckets:
 *   - matched              movement linked to an invoice
 *   - sin_factura          bank movement with no invoice (unmatched-in-statement)
 *   - sin_movimiento       invoice with no bank movement (unmatched-in-invoices)
 *   - fuera_periodo        movement whose date falls outside the statement window
 *
 * Re-running is safe: it clears prior *suggested* (un-confirmed) matches first,
 * so confirmed human decisions are never overwritten.
 */
class ReconciliationEngine
{
    public function __construct(
        private readonly ReconciliationMatcher $matcher,
    ) {}

    /**
     * @return array{matched:int, sin_factura:int, sin_movimiento:int, fuera_periodo:int}
     */
    public function reconcile(Period $period): array
    {
        $movements = BankMovement::whereHas('statement', fn ($q) =>
            $q->where('period_id', $period->id)
        )->get();

        $invoices = Invoice::where('period_id', $period->id)
            ->where('cancelado', false)
            ->get();

        // Clear only un-confirmed suggestions; keep human-confirmed matches.
        $this->clearSuggestions($period);

        // Which movements/invoices are already spoken for by confirmed matches?
        $confirmed = InvoiceMatch::where('period_id', $period->id)
            ->where('estado', 'confirmado')
            ->get(['bank_movement_id', 'invoice_id']);

        $lockedMovements = $confirmed->pluck('bank_movement_id')->all();
        $lockedInvoices  = $confirmed->pluck('invoice_id')->all();

        $freeMovements = $movements->whereNotIn('id', $lockedMovements)->values();
        $freeInvoices  = $invoices->whereNotIn('id', $lockedInvoices)->values();

        // Run the matcher on what's still free.
        $proposals = $this->matcher->match($freeMovements, $freeInvoices);

        DB::transaction(function () use ($proposals, $period) {
            foreach ($proposals as $p) {
                InvoiceMatch::create([
                    'client_id'        => $period->client_id,
                    'period_id'        => $period->id,
                    'bank_movement_id' => $p['movement_id'],
                    'invoice_id'       => $p['invoice_id'],
                    'metodo'           => 'deterministico',
                    'score'            => $p['score'],
                    'estado'           => 'sugerido',
                ]);
            }
        });

        $this->applyBuckets($period, $movements, $invoices);

        $summary = $this->summarize($period);
        $this->updatePeriod($period, $summary);

        return $summary;
    }

    /** Remove suggested (not confirmed) matches before re-running. */
    private function clearSuggestions(Period $period): void
    {
        InvoiceMatch::where('period_id', $period->id)
            ->where('estado', 'sugerido')
            ->delete();
    }

    /**
     * Set each movement's and invoice's estado_conciliacion based on whether it
     * participates in a match, and flag movements dated outside the statement
     * window as fuera_periodo.
     */
    private function applyBuckets(Period $period, $movements, $invoices): void
    {
        $matchedMovementIds = InvoiceMatch::where('period_id', $period->id)
            ->pluck('bank_movement_id')->all();
        $matchedInvoiceIds = InvoiceMatch::where('period_id', $period->id)
            ->pluck('invoice_id')->all();

        DB::transaction(function () use ($movements, $invoices, $matchedMovementIds, $matchedInvoiceIds) {
            foreach ($movements as $m) {
                if (in_array($m->id, $matchedMovementIds, true)) {
                    $estado = 'conciliado';
                } elseif ($this->outsideWindow($m)) {
                    $estado = 'fuera_periodo';
                } else {
                    $estado = 'sin_factura';
                }
                if ($m->estado_conciliacion !== $estado) {
                    $m->update(['estado_conciliacion' => $estado]);
                }
            }

            foreach ($invoices as $inv) {
                $estado = in_array($inv->id, $matchedInvoiceIds, true) ? 'conciliado' : 'sin_movimiento';
                if ($inv->estado_conciliacion !== $estado) {
                    $inv->update(['estado_conciliacion' => $estado]);
                }
            }
        });
    }

    /** A movement is "out of period" if it falls outside its statement's window. */
    private function outsideWindow(BankMovement $m): bool
    {
        $statement = $m->statement;
        if (! $statement || ! $statement->fecha_inicio || ! $statement->fecha_fin || ! $m->fecha) {
            return false;
        }

        return $m->fecha->lt($statement->fecha_inicio) || $m->fecha->gt($statement->fecha_fin);
    }

    /**
     * @return array{matched:int, sin_factura:int, sin_movimiento:int, fuera_periodo:int}
     */
    private function summarize(Period $period): array
    {
        $movementCounts = BankMovement::whereHas('statement', fn ($q) =>
            $q->where('period_id', $period->id)
        )->selectRaw('estado_conciliacion, count(*) as c')
         ->groupBy('estado_conciliacion')
         ->pluck('c', 'estado_conciliacion');

        $sinMovimiento = Invoice::where('period_id', $period->id)
            ->where('estado_conciliacion', 'sin_movimiento')
            ->count();

        return [
            'matched'       => (int) ($movementCounts['conciliado'] ?? 0),
            'sin_factura'   => (int) ($movementCounts['sin_factura'] ?? 0),
            'fuera_periodo' => (int) ($movementCounts['fuera_periodo'] ?? 0),
            'sin_movimiento'=> $sinMovimiento,
        ];
    }

    private function updatePeriod(Period $period, array $summary): void
    {
        $unmatched = $summary['sin_factura'] + $summary['sin_movimiento'];

        // If everything reconciled, the period is "matched"; if exceptions remain,
        // it "needs review". Either way it has advanced past extraction.
        $status = $unmatched === 0 && $summary['matched'] > 0
            ? PeriodStatus::Matched
            : PeriodStatus::NeedsReview;

        $period->update([
            'matched_count'   => $summary['matched'],
            'unmatched_count' => $unmatched,
            'status'          => $status,
        ]);
    }
}
