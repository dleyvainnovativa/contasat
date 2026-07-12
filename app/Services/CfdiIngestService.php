<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Period;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Turns an uploaded file (a ZIP of CFDIs, or a single XML) into persisted
 * Invoice + InvoiceLine rows for a given client + period.
 *
 * Deduplication is by UUID: re-ingesting the same package is safe and simply
 * skips invoices already present. Every XML is stored verbatim (SAT can demand
 * the original). Returns a summary the UI/job can surface.
 */
class CfdiIngestService
{
    public function __construct(
        private readonly CfdiParser $parser,
    ) {}

    /**
     * @return array{imported:int, skipped:int, failed:int, errors:array<int,string>}
     */
    public function ingestFile(string $absolutePath, Client $client, Period $period): array
    {
        $xmls = $this->extractXmls($absolutePath);

        if (empty($xmls)) {
            throw new RuntimeException('No se encontraron archivos XML en el paquete.');
        }

        $summary = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        foreach ($xmls as $name => $xml) {
            try {
                $result = $this->ingestSingle($xml, $client, $period);
                $summary[$result]++;   // 'imported' or 'skipped'
            } catch (Throwable $e) {
                $summary['failed']++;
                // Keep the error list bounded so a bad batch doesn't balloon.
                if (count($summary['errors']) < 25) {
                    $summary['errors'][] = "{$name}: {$e->getMessage()}";
                }
            }
        }

        $this->refreshPeriodCounters($period);

        return $summary;
    }

    /**
     * Persist one CFDI. Returns 'imported' or 'skipped' (already present).
     */
    private function ingestSingle(string $xml, Client $client, Period $period): string
    {
        $parsed = $this->parser->parse($xml, $client);
        $uuid   = $parsed['header']['uuid'];

        // Dedup: UUID is globally unique across the system.
        if (Invoice::where('uuid', $uuid)->exists()) {
            return 'skipped';
        }

        DB::transaction(function () use ($parsed, $xml, $client, $period) {
            $invoice = Invoice::create($parsed['header'] + [
                'client_id'    => $client->id,
                'period_id'    => $period->id,
                'xml_original' => $xml,
            ]);

            if (! empty($parsed['lines'])) {
                $invoice->lines()->createMany($parsed['lines']);
            }
        });

        return 'imported';
    }

    /**
     * Read every .xml out of the input. Accepts either a ZIP or a lone XML file.
     * @return array<string,string> filename => xml content
     */
    private function extractXmls(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Archivo no encontrado.');
        }

        // Single XML upload
        if (str_ends_with(strtolower($path), '.xml')) {
            return [basename($path) => (string) file_get_contents($path)];
        }

        $xmls = [];
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo ZIP.');
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);

            // Skip directories, macOS metadata, and non-xml entries.
            if (str_ends_with($entry, '/') || str_starts_with($entry, '__MACOSX') || ! str_ends_with(strtolower($entry), '.xml')) {
                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content !== false) {
                $xmls[$entry] = $content;
            }
        }

        $zip->close();

        return $xmls;
    }

    /** Keep the period's denormalized counters in sync after ingest. */
    private function refreshPeriodCounters(Period $period): void
    {
        $period->update([
            'invoice_count' => Invoice::where('period_id', $period->id)->count(),
        ]);
    }
}
