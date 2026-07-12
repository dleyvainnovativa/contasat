<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\BankMovement;
use Illuminate\Support\Collection;

/**
 * The deterministic reconciliation matcher.
 *
 * Given a period's bank movements and invoices, it scores every plausible
 * (movement, invoice) pair and then greedily assigns the highest-scoring pairs
 * without linking any movement or invoice twice. Amount is the dominant signal;
 * date proximity and RFC/reference presence refine it.
 *
 * The matcher is deterministic and side-effect free — it returns proposed links
 * with scores. Persisting them (as "sugerido" matches) and bucketing the leftovers
 * is the engine's job. AI is only brought in later for the genuinely ambiguous
 * remainder, never as the primary mechanism, so the numbers stay auditable.
 *
 * Direction logic:
 *   - emitida (income)  invoices are paid by deposits  -> match against `deposito`
 *   - recibida (expense) invoices are paid by charges   -> match against `cargo`
 */
class ReconciliationMatcher
{
    /** A pair must reach this score to be proposed at all. */
    private const MIN_SCORE = 0.55;

    /** Amount tolerance: matches within this fraction still count (partial credit). */
    private const AMOUNT_TOLERANCE = 0.02;   // 2%

    /** Beyond this many days apart, date contributes nothing. */
    private const MAX_DATE_DAYS = 45;

    /**
     * @param Collection<int,BankMovement> $movements
     * @param Collection<int,Invoice> $invoices
     * @return array<int, array{movement_id:int, invoice_id:int, score:float}>
     */
    public function match(Collection $movements, Collection $invoices): array
    {
        $candidates = [];

        foreach ($movements as $movement) {
            // The side of the movement that represents money moving for a payment.
            $movementAmount = $this->paymentAmount($movement);
            if ($movementAmount <= 0) {
                continue; // zero movement, nothing to match
            }

            foreach ($invoices as $invoice) {
                if (! $this->directionCompatible($movement, $invoice)) {
                    continue;
                }

                $score = $this->score($movement, $movementAmount, $invoice);
                if ($score >= self::MIN_SCORE) {
                    $candidates[] = [
                        'movement_id' => $movement->id,
                        'invoice_id'  => $invoice->id,
                        'score'       => round($score, 4),
                    ];
                }
            }
        }

        return $this->greedyAssign($candidates);
    }

    /**
     * The amount a movement represents for matching: a charge OR a deposit,
     * whichever is non-zero.
     */
    private function paymentAmount(BankMovement $m): float
    {
        return $m->cargo > 0 ? (float) $m->cargo : (float) $m->deposito;
    }

    /**
     * A deposit can only pay an emitida (income) invoice; a charge can only pay a
     * recibida (expense) invoice. This halves the search space and prevents
     * nonsensical links.
     */
    private function directionCompatible(BankMovement $m, Invoice $invoice): bool
    {
        if ($m->deposito > 0) {
            return $invoice->tipo === 'emitida';
        }
        if ($m->cargo > 0) {
            return $invoice->tipo === 'recibida';
        }

        return false;
    }

    /**
     * Weighted score in [0,1]:
     *   amount  0.60  — exact or within tolerance
     *   date    0.25  — closeness in days
     *   rfc/ref 0.15  — counterparty RFC or invoice folio present in description
     *
     * Amount is gating: if the amounts aren't within tolerance the pair is
     * essentially disqualified (amount component near zero drags the total below
     * MIN_SCORE), which is what we want — you don't reconcile a $500 movement to
     * a $5000 invoice no matter how close the date.
     */
    private function score(BankMovement $m, float $movementAmount, Invoice $invoice): float
    {
        $amountScore = $this->amountScore($movementAmount, (float) $invoice->total);
        $dateScore   = $this->dateScore($m, $invoice);
        $refScore    = $this->referenceScore($m, $invoice);

        return ($amountScore * 0.60) + ($dateScore * 0.25) + ($refScore * 0.15);
    }

    private function amountScore(float $movementAmount, float $invoiceTotal): float
    {
        if ($invoiceTotal <= 0) {
            return 0.0;
        }

        $diff = abs($movementAmount - $invoiceTotal);
        $rel  = $diff / $invoiceTotal;

        if ($rel < 0.0001) {
            return 1.0;                       // exact
        }
        if ($rel <= self::AMOUNT_TOLERANCE) {
            // Linear falloff within tolerance: at the edge, ~0.7.
            return 1.0 - ($rel / self::AMOUNT_TOLERANCE) * 0.3;
        }

        return 0.0;                           // outside tolerance: disqualifying
    }

    private function dateScore(BankMovement $m, Invoice $invoice): float
    {
        if (! $m->fecha || ! $invoice->fecha_emision) {
            return 0.0;
        }

        $days = abs($m->fecha->diffInDays($invoice->fecha_emision));

        if ($days > self::MAX_DATE_DAYS) {
            return 0.0;
        }

        // 1.0 at same day, decaying linearly to 0 at MAX_DATE_DAYS.
        return 1.0 - ($days / self::MAX_DATE_DAYS);
    }

    /**
     * Does the counterparty RFC or the invoice folio appear in the movement
     * description/reference? Bank descriptions often embed these.
     */
    private function referenceScore(BankMovement $m, Invoice $invoice): float
    {
        $haystack = mb_strtolower(($m->descripcion ?? '') . ' ' . ($m->referencia ?? ''));
        if ($haystack === ' ') {
            return 0.0;
        }

        $counterpartyRfc = $invoice->tipo === 'emitida'
            ? $invoice->receptor_rfc
            : $invoice->emisor_rfc;

        $score = 0.0;

        if ($counterpartyRfc && str_contains($haystack, mb_strtolower($counterpartyRfc))) {
            $score = 1.0;
        } elseif ($invoice->folio && mb_strlen((string) $invoice->folio) >= 3
                  && str_contains($haystack, mb_strtolower((string) $invoice->folio))) {
            $score = 0.7;
        }

        return $score;
    }

    /**
     * Greedy assignment: sort all candidate pairs by score descending, then take
     * each pair whose movement and invoice are both still free. Simple, fast, and
     * good enough — the amount gate makes conflicts rare in practice.
     *
     * @param array<int, array{movement_id:int, invoice_id:int, score:float}> $candidates
     * @return array<int, array{movement_id:int, invoice_id:int, score:float}>
     */
    private function greedyAssign(array $candidates): array
    {
        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        $usedMovements = [];
        $usedInvoices  = [];
        $assigned = [];

        foreach ($candidates as $pair) {
            if (isset($usedMovements[$pair['movement_id']]) || isset($usedInvoices[$pair['invoice_id']])) {
                continue;
            }
            $usedMovements[$pair['movement_id']] = true;
            $usedInvoices[$pair['invoice_id']]   = true;
            $assigned[] = $pair;
        }

        return $assigned;
    }
}
