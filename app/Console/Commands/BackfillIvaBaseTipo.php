<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\CfdiParser;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backfills invoice_lines.iva_base_tipo for invoices parsed before that field
 * existed, by re-parsing each invoice's stored xml_original with the same parser
 * used at ingest. Accurate (uses the real ObjetoImp/TipoFactor/TasaOCuota),
 * unlike guessing from stored amounts.
 *
 * Idempotent: only touches lines whose iva_base_tipo is still null, unless
 * --force is given. Lines are matched to parsed conceptos by position, which is
 * how they were originally created (createMany preserves order).
 *
 *   php artisan cfdi:backfill-iva-base            # only null lines
 *   php artisan cfdi:backfill-iva-base --force    # recompute all
 *   php artisan cfdi:backfill-iva-base --client=5 # limit to one client
 */
class BackfillIvaBaseTipo extends Command
{
    protected $signature = 'cfdi:backfill-iva-base
        {--force : Recompute even lines that already have a value}
        {--client= : Limit to a single client id}';

    protected $description = 'Derive invoice_lines.iva_base_tipo from stored XML for existing invoices';

    public function handle(CfdiParser $parser): int
    {
        $query = Invoice::query()
            ->whereNotNull('xml_original')
            ->with(['lines', 'client'])
            ->when($this->option('client'), fn($q) => $q->where('client_id', $this->option('client')));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No hay facturas con XML para procesar.');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $skipped = 0;
        $failed  = 0;

        $query->chunkById(200, function ($invoices) use ($parser, $force, &$updated, &$skipped, &$failed, $bar) {
            foreach ($invoices as $invoice) {
                $bar->advance();

                // Nothing to do if every line already classified and not forcing.
                $targets = $force
                    ? $invoice->lines
                    : $invoice->lines->whereNull('iva_base_tipo');

                if ($targets->isEmpty()) {
                    $skipped++;
                    continue;
                }

                try {
                    $parsed = $parser->parse($invoice->xml_original, $invoice->client);
                } catch (Throwable $e) {
                    $failed++;
                    continue;
                }

                $parsedLines = $parsed['lines'] ?? [];

                // Match by position: lines were created in concepto order.
                $orderedLines = $invoice->lines->values();
                foreach ($parsedLines as $i => $pLine) {
                    $line = $orderedLines->get($i);
                    if (! $line) {
                        continue;
                    }
                    if (! $force && $line->iva_base_tipo !== null) {
                        continue;
                    }
                    $tipo = $pLine['iva_base_tipo'] ?? null;
                    if ($tipo !== null && $line->iva_base_tipo !== $tipo) {
                        $line->update(['iva_base_tipo' => $tipo]);
                        $updated++;
                    }
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Líneas actualizadas: {$updated}");
        $this->line("Facturas sin cambios: {$skipped}");
        if ($failed > 0) {
            $this->warn("Facturas con XML no parseable: {$failed}");
        }

        return self::SUCCESS;
    }
}
