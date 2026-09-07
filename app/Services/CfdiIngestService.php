<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Period;
use Carbon\Carbon;
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
        private readonly \App\Services\PagoComplementService $pagoComplements,

    ) {}

    /**
     * Ingest a package, routing each invoice to the period matching its own
     * fecha_emision (creating that period on demand, within the client's valid
     * range). Invoices dated in the future or before the client's inicio de
     * operaciones are omitted rather than forced into the active period.
     *
     * The $fallbackPeriod is used only when an invoice has no parseable date
     * (rare) — it keeps such an edge case from being silently dropped.
     *
     * @return array{
     *   imported:int, skipped:int, omitidas:int, failed:int,
     *   errors:array<int,string>, omitida_reasons:array<int,string>,
     *   by_period:array<int,int>
     * }
     */
    public function ingestFile(string $absolutePath, Client $client, Period $fallbackPeriod): array
    {
        $xmls = $this->extractXmls($absolutePath);

        if (empty($xmls)) {
            throw new RuntimeException('No se encontraron archivos XML en el paquete.');
        }

        $summary = [
            'imported'        => 0,
            'skipped'         => 0,
            'omitidas'        => 0,
            'failed'          => 0,
            'errors'          => [],
            'omitida_reasons' => [],
            'by_period'       => [],   // period_id => imported count
        ];

        foreach ($xmls as $name => $xml) {
            try {
                $result = $this->ingestSingle($xml, $client, $fallbackPeriod);

                $summary[$result['status']]++;   // 'imported' | 'skipped' | 'omitidas'

                if ($result['status'] === 'imported' && $result['period_id']) {
                    $pid = $result['period_id'];
                    $summary['by_period'][$pid] = ($summary['by_period'][$pid] ?? 0) + 1;
                }

                if ($result['status'] === 'omitidas' && count($summary['omitida_reasons']) < 25) {
                    $summary['omitida_reasons'][] = "{$name}: {$result['reason']}";
                }
            } catch (Throwable $e) {
                $summary['failed']++;
                // Keep the error list bounded so a bad batch doesn't balloon.
                if (count($summary['errors']) < 25) {
                    $summary['errors'][] = "{$name}: {$e->getMessage()}";
                }
            }
        }

        // Refresh counters for every period that actually received invoices.
        foreach (array_keys($summary['by_period']) as $periodId) {
            if ($period = Period::find($periodId)) {
                $this->refreshPeriodCounters($period);
            }
        }

        return $summary;
    }

    /**
     * Persist one CFDI, routing it to the period matching its fecha_emision.
     *
     * @return array{status:string, period_id:?int, reason:?string}
     *   status: 'imported' | 'skipped' (dup UUID) | 'omitidas' (out of range)
     */
    private function ingestSingle(string $xml, Client $client, Period $fallbackPeriod): array
    {
        $parsed = $this->parser->parse($xml, $client);
        $uuid   = $parsed['header']['uuid'];

        // Dedup: UUID is globally unique across the system.
        if (Invoice::where('uuid', $uuid)->exists()) {
            return ['status' => 'skipped', 'period_id' => null, 'reason' => null];
        }

        // Route by the invoice's own date. Out-of-range invoices (future, or
        // before the client's inicio de operaciones) are omitted, not imported.
        $period = $this->resolvePeriod($client, $parsed['header']['fecha_emision'] ?? null, $fallbackPeriod);

        if ($period === null) {
            return [
                'status'    => 'omitidas',
                'period_id' => null,
                'reason'    => 'Fecha fuera del rango de operaciones del cliente.',
            ];
        }

        DB::transaction(function () use ($parsed, $xml, $client, $period) {
            $invoice = Invoice::create($parsed['header'] + [
                'client_id'    => $client->id,
                'period_id'    => $period->id,
                'xml_original' => $xml,
            ]);
            if ($invoice->tipo_comprobante === 'P') {
                $this->pagoComplements->process($invoice, $xml);
            }

            if (! empty($parsed['lines'])) {
                $invoice->lines()->createMany($parsed['lines']);
            }
        });

        return ['status' => 'imported', 'period_id' => $period->id, 'reason' => null];
    }

    /**
     * Resolve (creating if needed) the period matching an invoice's date for
     * this client. Returns null when the date is out of the client's valid
     * range. Falls back to the given period only when the date is unparseable.
     */
    private function resolvePeriod(Client $client, ?string $fechaEmision, Period $fallbackPeriod): ?Period
    {
        if (! $fechaEmision) {
            return $fallbackPeriod;
        }

        try {
            $fecha = Carbon::parse($fechaEmision);
        } catch (Throwable) {
            return $fallbackPeriod;
        }

        $year  = (int) $fecha->year;
        $month = (int) $fecha->month;

        if (! $client->periodInRange($year, $month)) {
            return null;
        }

        return Period::firstOrCreate([
            'client_id' => $client->id,
            'year'      => $year,
            'month'     => $month,
        ]);
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
