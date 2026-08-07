<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Period;
use App\Services\InvoiceClassificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Classifies every unclassified invoice in a period: mints counterparty
 * subaccounts and runs the AI abono suggestion. Queued because the AI calls add
 * up across a month's invoices. Human-confirmed classifications are left alone.
 */
class ClassifyPeriodInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 1;

    public function __construct(public int $periodId) {}

    public function handle(InvoiceClassificationService $classifier): void
    {
        $period = Period::find($this->periodId);
        if (! $period) {
            return;
        }

        Invoice::where('period_id', $period->id)
            ->where('cancelado', false)
            ->where('clasificacion', '!=', 'clasificada')
            ->with(['client', 'lines'])
            ->chunkById(50, function ($invoices) use ($classifier) {
                foreach ($invoices as $invoice) {
                    $classifier->classify($invoice);
                }
            });
    }
}
