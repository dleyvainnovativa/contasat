<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\NominaComplementParser;
use Illuminate\Console\Command;

/**
 * Parses the nómina complement out of the stored XML for existing tipo-N
 * invoices and fills invoices.nomina_data, so historical recibos populate the
 * wide nómina table. Idempotent; re-run with --force to re-parse rows already set.
 *
 *   php artisan nomina:backfill
 *   php artisan nomina:backfill --client=5 --force
 */
class BackfillNominaData extends Command
{
    protected $signature = 'nomina:backfill
        {--client= : Limit to a single client id}
        {--force : Re-parse invoices that already have nomina_data}';

    protected $description = 'Backfill invoices.nomina_data from stored XML for tipo-N invoices';

    public function handle(NominaComplementParser $parser): int
    {
        $query = Invoice::query()
            ->where('tipo_comprobante', 'N')
            ->whereNotNull('xml_original')
            ->when($this->option('client'), fn ($q) => $q->where('client_id', $this->option('client')))
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('nomina_data'));

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No hay recibos de nómina por procesar.');
            return self::SUCCESS;
        }

        $this->line("Recibos de nómina a procesar: {$total}");
        $bar = $this->output->createProgressBar($total);

        $ok = 0; $empty = 0;
        $query->select('id', 'xml_original')->chunkById(200, function ($rows) use ($parser, &$ok, &$empty, $bar) {
            foreach ($rows as $inv) {
                $data = $parser->parse($inv->xml_original);
                if ($data) {
                    $inv->update(['nomina_data' => $data]);
                    $ok++;
                } else {
                    $empty++;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Listo. Con complemento: {$ok}. Sin complemento nómina: {$empty}.");

        return self::SUCCESS;
    }
}
