<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountClientStatus;
use App\Models\Invoice;
use App\Models\PolizaLine;
use Illuminate\Support\Collection;

/**
 * Per-client activate / inactivate of chart-of-accounts rows.
 *
 * Active state is per client (see account_client_status): for a global account
 * it lives in the overlay; for a client-owned account it's the row's own `activo`.
 *
 * Rules:
 *   - Activate is a single account, always allowed (inactive -> active), and
 *     independent of the parent (a son can be activated while the father stays
 *     inactive).
 *   - Inactivate cascades to the whole branch, but is ALL-OR-NOTHING: if the
 *     account or ANY descendant has movimientos registered for this client, the
 *     whole operation is aborted (nothing is inactivated) and the offending
 *     accounts are reported. You can't inactivate a father while a child is in use.
 */
class AccountStatusService
{
    /** Overlay map [account_id => bool activo] for a client, for display. */
    public function overlayMap(int $clientId): array
    {
        return AccountClientStatus::where('client_id', $clientId)
            ->pluck('activo', 'account_id')
            ->map(fn ($v) => (bool) $v)
            ->all();
    }

    /**
     * Apply an activate/inactivate. Returns a summary of what happened.
     *
     * @return array{action:string, changed:array<int,string>, blocked:array<int,string>, aborted:bool}
     */
    public function toggle(Account $account, bool $activo, int $clientId): array
    {
        if ($activo) {
            $this->setActive($account, true, $clientId);
            return ['action' => 'activada', 'changed' => [$account->numero_cuenta], 'blocked' => [], 'aborted' => false];
        }

        $branch = $this->branch($account, $clientId);

        // All-or-nothing: if any node in the branch carries movimientos, abort.
        $blocked = $branch
            ->filter(fn ($node) => $this->hasInvoices($node->id, $clientId))
            ->pluck('numero_cuenta')
            ->values()
            ->all();

        if ($blocked) {
            return ['action' => 'inactivada', 'changed' => [], 'blocked' => $blocked, 'aborted' => true];
        }

        $changed = [];
        foreach ($branch as $node) {
            $this->setActive($node, false, $clientId);
            $changed[] = $node->numero_cuenta;
        }

        return ['action' => 'inactivada', 'changed' => $changed, 'blocked' => [], 'aborted' => false];
    }

    /** Does this account carry any register of invoices for this client? */
    public function hasInvoices(int $accountId, int $clientId): bool
    {
        $onInvoices = Invoice::where('client_id', $clientId)
            ->where(fn ($q) => $q->where('cuenta_contable_id', $accountId)
                ->orWhere('cuenta_abono_id', $accountId))
            ->exists();

        if ($onInvoices) {
            return true;
        }

        return PolizaLine::where('account_id', $accountId)
            ->whereHas('poliza', fn ($q) => $q->where('client_id', $clientId))
            ->exists();
    }

    /** The account plus every descendant visible to this client (global or owned). */
    private function branch(Account $account, int $clientId): Collection
    {
        $nodes   = collect([$account]);
        $queue   = [$account->id];
        $visited = [$account->id => true];

        while ($queue) {
            $parentIds = $queue;
            $queue = [];

            $children = Account::whereIn('parent_id', $parentIds)
                ->where(fn ($q) => $q->whereNull('client_id')->orWhere('client_id', $clientId))
                ->get();

            foreach ($children as $child) {
                if (isset($visited[$child->id])) {
                    continue;
                }
                $visited[$child->id] = true;
                $nodes->push($child);
                $queue[] = $child->id;
            }
        }

        return $nodes;
    }

    /** Write the effective active state: overlay for global rows, the row itself for client-owned. */
    private function setActive(Account $account, bool $activo, int $clientId): void
    {
        if ($account->client_id === null) {
            AccountClientStatus::updateOrCreate(
                ['client_id' => $clientId, 'account_id' => $account->id],
                ['activo' => $activo],
            );
            return;
        }

        $account->update(['activo' => $activo]);
    }
}
