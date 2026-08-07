<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resolves the counterparty subaccount for an RFC, minting it on first sight.
 *
 * Customers post under 105.01 (national) or 105.02 (foreign); suppliers under
 * their equivalents. Each distinct counterparty RFC gets its own sequential
 * subaccount — 105.01.1, 105.01.2, … — created once and reused thereafter, so
 * the same customer always resolves to the same account.
 *
 * Nationality rule:
 *   - A Mexican RFC is 12 (moral) or 13 (física) chars of the SAT pattern.
 *   - Foreign counterparties use the generic XEXX010101000, or have no RFC.
 *   => XEXX010101000 or blank/non-Mexican  -> foreign parent
 *      otherwise                            -> national parent
 *
 * This reuses the "remember the mapping" idea from account_defaults, but here the
 * mapping lives on the Account row itself (rfc_asociado), because the subaccount
 * IS the thing we're remembering.
 */
class CounterpartyAccountService
{
    private const FOREIGN_RFC = 'XEXX010101000';

    /** Mexican RFC: 3–4 letters, 6 date digits, 3 homoclave chars. */
    private const MX_RFC = '/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/i';

    /**
     * Get (or create) the subaccount for a counterparty under the given parent
     * agrupador — '105.01'/'105.02' for customers, or a supplier parent.
     *
     * @param string $nationalParent  agrupador code for national counterparties
     * @param string $foreignParent   agrupador code for foreign counterparties
     */
    public function resolve(
        Client $client,
        string $rfc,
        string $nombre,
        string $nationalParent,
        string $foreignParent,
    ): Account {
        $rfc = strtoupper(trim($rfc));
        $parentCode = $this->isForeign($rfc) ? $foreignParent : $nationalParent;

        // Already minted for this RFC?
        $existing = Account::clientOwned($client->id)
            ->where('rfc_asociado', $rfc)
            ->where('codigo_agrupador', $parentCode)
            ->first();

        if ($existing) {
            return $existing;
        }

        return $this->mint($client, $rfc, $nombre, $parentCode);
    }

    public function isForeign(string $rfc): bool
    {
        $rfc = strtoupper(trim($rfc));

        if ($rfc === '' || $rfc === self::FOREIGN_RFC) {
            return true;
        }

        return ! preg_match(self::MX_RFC, $rfc);
    }

    /**
     * Create the next sequential subaccount under $parentCode. Wrapped in a
     * transaction with a row-lock on the parent so two invoices for a new RFC
     * processed concurrently can't mint the same number.
     */
    private function mint(Client $client, string $rfc, string $nombre, string $parentCode): Account
    {
        return DB::transaction(function () use ($client, $rfc, $nombre, $parentCode) {
            $parent = Account::global()
                ->where('codigo_agrupador', $parentCode)
                ->lockForUpdate()->first();

            if (! $parent) {
                throw new RuntimeException(
                    "No existe la cuenta padre {$parentCode} en el catálogo del cliente. "
                        . 'Importa el catálogo SAT primero.'
                );
            }

            $next = Account::clientOwned($client->id)
                ->where('parent_id', $parent->id)
                ->where('auto_generada', true)
                ->count() + 1;

            $numero = "{$parentCode}.{$next}";

            return Account::create([
                'client_id'        => $client->id,
                'parent_id'        => $parent->id,
                'codigo_agrupador' => $parentCode,   // subaccounts share the parent's agrupador
                'numero_cuenta'    => $numero,
                'rfc_asociado'     => $rfc,
                'nombre'           => $this->accountName($nombre, $rfc),
                'nivel'            => $parent->nivel + 1,
                'naturaleza'       => $parent->naturaleza,
                'es_afectable'     => true,          // subaccounts are postable
                'auto_generada'    => true,
                'activo'           => true,
            ]);
        });
    }

    private function accountName(string $nombre, string $rfc): string
    {
        $nombre = trim($nombre);

        return $nombre !== '' ? mb_substr($nombre, 0, 200) : $rfc;
    }
}
