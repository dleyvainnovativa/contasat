<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Removes notas de crédito / egresos (TipoDeComprobante "E") that were imported
 * before the "no cargar notas de crédito" rule existed. New ingests skip them
 * (see CfdiIngestService), but rows already in the database stay until purged.
 *
 * SAFE BY DEFAULT: a dry run that only lists what it would delete. Pass --force
 * to actually delete. Deletion cascades to invoice_lines via its FK. This is
 * destructive — the XML is gone with the row — so it is opt-in and never runs
 * on its own.
 *
 *   php artisan cfdi:purge-notas-credito            # dry run (lists only)
 *   php artisan cfdi:purge-notas-credito --force    # actually delete
 *   php artisan cfdi:purge-notas-credito --client=5 # limit to one client
 */
class PurgeNotasCredito extends Command
{
    protected $signature = 'cfdi:purge-notas-credito
        {--force : Actually delete (default is a dry run that only lists)}
        {--client= : Limit to a single client id}';

    protected $description = 'Delete already-imported notas de crédito (tipo E) — dry run unless --force';

    public function handle(): int
    {
        $query = Invoice::query()
            ->where('tipo_comprobante', 'E')
            ->when($this->option('client'), fn($q) => $q->where('client_id', $this->option('client')));

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No hay notas de crédito (tipo E) almacenadas.');
            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn("Dry run: se eliminarían {$count} nota(s) de crédito (tipo E).");
            $this->line('Vuelve a ejecutar con --force para eliminarlas.');
            (clone $query)->limit(15)->get(['uuid', 'serie', 'folio', 'emisor_rfc', 'total'])
                ->each(fn($i) => $this->line("  {$i->serie}{$i->folio}  {$i->emisor_rfc}  \${$i->total}  {$i->uuid}"));
            return self::SUCCESS;
        }

        $deleted = 0;
        $query->chunkById(200, function ($invoices) use (&$deleted) {
            foreach ($invoices as $invoice) {
                $invoice->delete(); // cascades to lines / payment_documents
                $deleted++;
            }
        });

        $this->info("Notas de crédito eliminadas: {$deleted}");

        return self::SUCCESS;
    }
}
