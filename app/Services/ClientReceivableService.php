<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Account;

/**
 * Mints a client's own receivable subaccount (Reading B: consolidated ledger,
 * each client is a customer of the firm).
 *
 * On client creation we create their 105.01.# (national) or 105.02.# (foreign)
 * subaccount under the GLOBAL parent, keyed on the client's own RFC. This is the
 * receivable that the póliza de provisión debits and the póliza de cobro clears.
 *
 * It reuses CounterpartyAccountService — the client is, mechanically, just a
 * counterparty whose RFC happens to be the client's own. The subaccount is
 * stamped with the client's id AND rfc_asociado = the client's RFC, so it's
 * found again on every subsequent operation for that client.
 */
class ClientReceivableService
{
    // Customer parents (same as CounterpartyAccountService customer side).
    private const NATIONAL = '105.01';
    private const FOREIGN  = '105.02';

    public function __construct(
        private readonly CounterpartyAccountService $counterparty,
    ) {}

    /**
     * Ensure the client has their receivable subaccount. Idempotent: returns the
     * existing one if already minted (e.g. on update, or a re-run).
     */
    public function ensureFor(Client $client): Account
    {
        return $this->counterparty->resolve(
            $client,
            $client->rfc,
            $client->razon_social ?? $client->nombre_comercial ?? $client->rfc,
            self::NATIONAL,
            self::FOREIGN,
        );
    }

    /** The client's receivable subaccount, if it exists. */
    public function forClient(Client $client): ?Account
    {
        return Account::where('client_id', $client->id)
            ->where('rfc_asociado', strtoupper(trim($client->rfc)))
            ->whereIn('codigo_agrupador', [self::NATIONAL, self::FOREIGN])
            ->first();
    }
}
