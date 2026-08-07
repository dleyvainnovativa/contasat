<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\PagoComplementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Parses pago complements for existing tipo-P invoices that don't yet have their
 * DoctoRelacionado links stored. Use this once after deploying Block C to backfill
 * payment complements ingested before the parser existed; new ingests should call
 * PagoComplementService inline (see changelist).
 *
 * Reads each invoice's stored original XML — Phase 1 keeps it — and processes it.
 */
class BackfillPagoComplements implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 1;

    public function __construct(public int $clientId) {}

    public function handle(PagoComplementService $service): void
    {
        Invoice::where('client_id', $this->clientId)
            ->where('tipo_comprobante', 'P')
            ->whereNotNull('xml_original')   // adjust to your column for stored XML
            ->chunkById(100, function ($invoices) use ($service) {
                foreach ($invoices as $invoice) {
                    $xml = $this->loadXml($invoice);
                    if ($xml !== null) {
                        $service->process($invoice, $xml);
                    }
                }
            });

        // Resolve any links whose paid invoice has since been ingested.
        $service->relinkUnresolved($this->clientId);
    }

    /**
     * Load the stored CFDI XML for an invoice. Adjust to however Phase 1 persisted
     * it — a DB column (xml_original) or a file path under storage.
     */
    private function loadXml(Invoice $invoice): ?string
    {
        // If stored in the DB:
        if (! empty($invoice->xml_original)) {
            return $invoice->xml_original;
        }

        // If stored as a file (uncomment and adjust):
        // $path = storage_path('app/cfdi/' . $invoice->client_id . '/' . $invoice->uuid . '.xml');
        // return is_file($path) ? file_get_contents($path) : null;

        return null;
    }
}
