<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Repairs nómina invoices classified before the nómina rule existed.
 *
 * Nómina CFDIs (tipo_comprobante = 'N') were run through the generic classifier,
 * which minted an employee counterparty subaccount under 105.01 and (sometimes)
 * suggested a 4xx revenue abono. Both are wrong: nómina is not income. The
 * correct contraparte is 601.01 (Sueldos y salarios), with no abono.
 *
 * This command:
 *   1. Re-points every nómina invoice's cuenta_contable_id to 601.01 (per client),
 *      clears cuenta_abono_id, and marks it 'clasificada'.
 *   2. Deactivates the now-orphaned employee subaccounts it freed up — only ones
 *      that were auto-generated AND are no longer referenced by ANY invoice, so a
 *      105.xx that also serves a real customer is left untouched.
 *
 * Idempotent (safe to re-run) and reversible (accounts are deactivated, not
 * deleted). Run from the project root:
 *
 *   php artisan cfdi:fix-nomina-accounts            # all clients
 *   php artisan cfdi:fix-nomina-accounts --client=5 # one client
 *   php artisan cfdi:fix-nomina-accounts --dry-run  # report only, no writes
 */
class FixNominaAccounts extends Command
{
    private const NOMINA_CONTABLE = '601.01';

    protected $signature = 'cfdi:fix-nomina-accounts
        {--client= : Limit to a single client id}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Re-point nómina invoices to 601.01 and deactivate orphaned employee accounts';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Invoice::query()
            ->where('tipo_comprobante', 'N')
            ->when($this->option('client'), fn ($q) => $q->where('client_id', $this->option('client')));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No hay facturas de nómina por procesar.');
            return self::SUCCESS;
        }

        $this->line("Facturas de nómina encontradas: {$total}");

        $nominaAccounts = [];          // client_id => Account|null (601.01), resolved once per client
        $freedAccountIds = [];         // old employee counterparty account ids we unlinked
        $reassigned = 0;
        $skippedNoAccount = 0;

        $query->with('client')->chunkById(200, function ($invoices) use (
            &$nominaAccounts, &$freedAccountIds, &$reassigned, &$skippedNoAccount, $dryRun
        ) {
            foreach ($invoices as $invoice) {
                $clientId = $invoice->client_id;

                if (! array_key_exists($clientId, $nominaAccounts)) {
                    $nominaAccounts[$clientId] = $this->nominaAccount($clientId);
                }
                $cuenta = $nominaAccounts[$clientId];

                if (! $cuenta) {
                    $skippedNoAccount++;
                    continue; // 601.01 not in this client's catalog — leave as-is
                }

                // Remember the old counterparty so we can clean it up afterward.
                if ($invoice->cuenta_contable_id && $invoice->cuenta_contable_id !== $cuenta->id) {
                    $freedAccountIds[$invoice->cuenta_contable_id] = true;
                }

                $alreadyCorrect = $invoice->cuenta_contable_id === $cuenta->id
                    && $invoice->cuenta_abono_id === null
                    && $invoice->clasificacion === 'clasificada';

                if ($alreadyCorrect) {
                    continue;
                }

                if (! $dryRun) {
                    $invoice->update([
                        'cuenta_contable_id' => $cuenta->id,
                        'cuenta_abono_id'    => null,
                        'clasificacion'      => 'clasificada',
                    ]);
                }
                $reassigned++;
            }
        });

        $this->line(($dryRun ? '[dry-run] ' : '') . "Facturas re-apuntadas a 601.01: {$reassigned}");
        if ($skippedNoAccount > 0) {
            $this->warn("Facturas sin 601.01 en el catálogo (omitidas): {$skippedNoAccount}");
        }

        // Deactivate orphaned employee accounts: auto-generated and no longer
        // referenced by any invoice's cuenta_contable_id.
        $deactivated = 0;
        foreach (array_keys($freedAccountIds) as $accountId) {
            $account = Account::find($accountId);
            if (! $account || ! $account->auto_generada || ! $account->activo) {
                continue;
            }

            $stillUsed = Invoice::where('cuenta_contable_id', $accountId)->exists();
            if ($stillUsed) {
                continue; // shared with a real counterparty — keep it active
            }

            if (! $dryRun) {
                $account->update(['activo' => false]);
            }
            $deactivated++;
        }

        $this->line(($dryRun ? '[dry-run] ' : '') . "Cuentas de empleado desactivadas: {$deactivated}");
        $this->newLine();
        $this->info($dryRun ? 'Dry-run completo (sin cambios escritos).' : 'Listo.');

        return self::SUCCESS;
    }

    /** The 601.01 account for a client (client-owned if present, else the global catalog row). */
    private function nominaAccount(int $clientId): ?Account
    {
        return Account::forClient($clientId)
            ->where('numero_cuenta', self::NOMINA_CONTABLE)
            ->where('activo', true)
            ->orderByRaw('client_id is null')
            ->first();
    }
}
