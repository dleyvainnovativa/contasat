<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;

/**
 * Reconciles the global chart of accounts (client_id IS NULL) with the client's
 * updated SAT Anexo-24 catalog, bundled at database/data/sat_catalog_agrupador.json.
 *
 * Matched by numero_cuenta == código agrupador, it sets per account:
 *   - tipo            : 'Afectable' | 'Agrupador'  (new SAT type)
 *   - es_afectable    : kept in step (Afectable => true, Agrupador => false)
 *   - naturaleza      : 'D' | 'A'  (the catalog's corrections)
 *   - activo          : false when the catalog marks the row DESACTIVAR
 *
 * Accounts not in the catalog (per-client subaccounts and local extras) are left
 * as-is, except their `tipo` is backfilled from es_afectable so the column is
 * never null. Idempotent and safe to re-run.
 *
 *   php artisan accounts:sync-catalog            # apply
 *   php artisan accounts:sync-catalog --dry-run  # report only
 */
class SyncAccountCatalog extends Command
{
    protected $signature = 'accounts:sync-catalog {--dry-run : Report changes without writing}';

    protected $description = 'Sync global accounts (tipo, es_afectable, naturaleza, activo) with the SAT catalog xlsx';

    public function handle(): int
    {
        $dry  = (bool) $this->option('dry-run');
        $path = database_path('data/sat_catalog_agrupador.json');

        if (! is_file($path)) {
            $this->error("No se encontró el catálogo: {$path}");
            return self::FAILURE;
        }

        $catalog = collect(json_decode(file_get_contents($path), true))
            ->keyBy(fn ($r) => (string) $r['codigo']);

        $this->line("Catálogo: {$catalog->count()} códigos. " . ($dry ? '[dry-run]' : ''));

        $counts = ['tipo' => 0, 'es_afectable' => 0, 'naturaleza' => 0, 'desactivar' => 0, 'sin_catalogo' => 0];
        $unmatched = [];

        // 1) Global accounts: reconcile against the catalog.
        Account::whereNull('client_id')->chunkById(500, function ($rows) use ($catalog, &$counts, &$unmatched, $dry) {
            foreach ($rows as $acc) {
                $row = $catalog->get((string) $acc->numero_cuenta);

                if (! $row) {
                    // Global account not in the catalog (local extra): just keep tipo populated.
                    $unmatched[] = $acc->numero_cuenta;
                    $this->applyTipoFromAfectable($acc, $dry, $counts);
                    continue;
                }

                $changes = [];

                if ($acc->tipo !== $row['tipo']) {
                    $changes['tipo'] = $row['tipo'];
                    $counts['tipo']++;
                }

                $wantAfectable = $row['tipo'] === 'Afectable';
                if ((bool) $acc->es_afectable !== $wantAfectable) {
                    $changes['es_afectable'] = $wantAfectable;
                    $counts['es_afectable']++;
                }

                if ($row['naturaleza'] && $acc->naturaleza !== $row['naturaleza']) {
                    $changes['naturaleza'] = $row['naturaleza'];
                    $counts['naturaleza']++;
                }

                if (($row['estado'] ?? null) === 'DESACTIVAR' && $acc->activo) {
                    $changes['activo'] = false;
                    $counts['desactivar']++;
                }

                if ($changes && ! $dry) {
                    $acc->update($changes);
                }
            }
        });

        // 2) Per-client subaccounts + any still-null tipo: backfill tipo from es_afectable.
        Account::whereNotNull('client_id')->whereNull('tipo')->chunkById(500, function ($rows) use (&$counts, $dry) {
            foreach ($rows as $acc) {
                $this->applyTipoFromAfectable($acc, $dry, $counts);
            }
        });

        $this->newLine();
        $this->table(
            ['tipo', 'es_afectable', 'naturaleza', 'DESACTIVAR', 'sin catálogo'],
            [[$counts['tipo'], $counts['es_afectable'], $counts['naturaleza'], $counts['desactivar'], $counts['sin_catalogo']]]
        );

        if ($unmatched) {
            $this->warn('Cuentas globales fuera del catálogo (sin tocar salvo tipo): ' . implode(', ', $unmatched));
        }

        $this->info($dry ? 'Dry-run completo, sin cambios escritos.' : 'Catálogo sincronizado.');

        return self::SUCCESS;
    }

    /** Fill a null `tipo` from es_afectable so the column is never empty. */
    private function applyTipoFromAfectable(Account $acc, bool $dry, array &$counts): void
    {
        if ($acc->tipo !== null) {
            return;
        }
        $tipo = $acc->es_afectable ? 'Afectable' : 'Agrupador';
        $counts['sin_catalogo']++;
        if (! $dry) {
            $acc->update(['tipo' => $tipo]);
        }
    }
}
