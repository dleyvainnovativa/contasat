<?php

namespace App\Services;

use App\Models\AccountDefault;
use App\Models\BankMovement;
use App\Models\Invoice;
use App\Models\InvoiceMatch;

/**
 * Account assignment for reconciled movements, with learned defaults.
 *
 * When a movement is matched to an invoice, it should post to a specific account.
 * Rather than asking every time, we remember "counterparty RFC X -> account Y"
 * per client. Next time that RFC shows up, the account pre-fills. Confirming an
 * assignment reinforces it (veces_usado++), so the most-used account wins.
 *
 * This is what makes account assignment tractable at 50+ clients: after the first
 * month or two, most movements auto-assign and the accountant only touches new
 * counterparties.
 */
class AccountAssignmentService
{
    /**
     * Suggested account_id for a movement, from the learned defaults, based on the
     * counterparty RFC of its matched invoice. Null when there's nothing learned yet.
     */
    public function suggestFor(BankMovement $movement): ?int
    {
        $rfc = $this->counterpartyRfc($movement);
        if (! $rfc) {
            return null;
        }

        $default = AccountDefault::where('client_id', $movement->client_id)
            ->where('rfc_contraparte', $rfc)
            ->first();

        return $default?->account_id;
    }

    /**
     * Assign an account to a movement and reinforce the learned default for that
     * counterparty RFC.
     */
    public function assign(BankMovement $movement, int $accountId): void
    {
        $movement->update(['account_id' => $accountId]);

        $rfc = $this->counterpartyRfc($movement);
        if (! $rfc) {
            return;
        }

        $default = AccountDefault::firstOrNew([
            'client_id'       => $movement->client_id,
            'rfc_contraparte' => $rfc,
        ]);

        // If the account changed, reset the counter to reflect the new choice.
        if ($default->exists && $default->account_id === $accountId) {
            $default->veces_usado++;
        } else {
            $default->account_id = $accountId;
            $default->veces_usado = 1;
        }

        $default->ultimo_uso_at = now();
        $default->save();
    }

    /**
     * The counterparty RFC for a movement = the "other party" on its matched
     * invoice. For an emitida (income) invoice that's the receptor; for a recibida
     * (expense) invoice that's the emisor.
     */
    private function counterpartyRfc(BankMovement $movement): ?string
    {
        $match = InvoiceMatch::where('bank_movement_id', $movement->id)->first();
        if (! $match) {
            return null;
        }

        $invoice = Invoice::find($match->invoice_id);
        if (! $invoice) {
            return null;
        }

        return $invoice->tipo === 'emitida'
            ? $invoice->receptor_rfc
            : $invoice->emisor_rfc;
    }
}
