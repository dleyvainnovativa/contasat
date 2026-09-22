<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\CfdiParser;
use Illuminate\Console\Command;
use Throwable;

/**
 * Backfills invoices.tipo_relacion for invoices parsed before that field
 * existed, by re-parsing each invoice's stored xml_original with the same parser
 * used at ingest. Accurate (reads the real CfdiRelacionados/@TipoRelacion),
 * and no re-download is needed because the XML is retained verbatim.
 *
 * Idempotent: only touches invoices whose tipo_relacion is still null, unless
 * --force is given. Invoices with no CfdiRelacionados stay null (correct — they
 * are plain "Factura" rows).
 *
 *   php artisan cfdi:backfill-tipo-relacion            # only null rows
 *   php artisan cfdi:backfill-tipo-relacion --force    # recompute all
 *   php artisan cfdi:backfill-tipo-relacion --client=5 # limit to one client
 */
class BackfillTipoRelacion extends Command
{
    protected $signature = 'cfdi:backfill-tipo-relacion
        {--force : Recompute even invoices that already have a value}
        {--client= : Limit to a single client id}';

    protected $description = 'Derive invoices.tipo_relacion from stored XML for existing invoices';

    public function handle(CfdiParser $parser): int
    {
        $query = Invoice::query()
            ->whereNotNull('xml_original')
            ->with('client')
            ->when(! $this->option('force'), fn($q) => $q->whereNull('tipo_relacion'))
            ->when($this->option('client'), fn($q) => $q->where('client_id', $this->option('client')));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No hay facturas por procesar.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $sinRelacion = 0;
        $failed  = 0;

        $query->chunkById(200, function ($invoices) use ($parser, &$updated, &$sinRelacion, &$failed, $bar) {
            foreach ($invoices as $invoice) {
                $bar->advance();

                try {
                    $parsed = $parser->parse($invoice->xml_original, $invoice->client);
                } catch (Throwable $e) {
                    $failed++;
                    continue;
                }

                $tipo = $parsed['header']['tipo_relacion'] ?? null;

                if ($tipo === null) {
                    $sinRelacion++;
                    continue;
                }

                if ($invoice->tipo_relacion !== $tipo) {
                    $invoice->update(['tipo_relacion' => $tipo]);
                    $updated++;
                }
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Facturas actualizadas con tipo de relación: {$updated}");
        $this->line("Facturas sin relación (quedan como Factura): {$sinRelacion}");
        if ($failed > 0) {
            $this->warn("Facturas con XML no parseable: {$failed}");
        }

        return self::SUCCESS;
    }
}
