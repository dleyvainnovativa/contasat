<?php

namespace App\Jobs;

use App\Enums\PeriodStatus;
use App\Models\CfdiUpload;
use App\Services\CfdiIngestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ClassifyPeriodInvoices;
use Throwable;

/**
 * Processes an uploaded CFDI package off the request cycle. On Hostinger the
 * database queue is drained by cron (`queue:work --stop-when-empty`), so this
 * runs shortly after upload rather than instantly — the UI polls for status.
 *
 * Advances the period to "Downloaded" once invoices land, so the dashboard state
 * machine reflects progress.
 */
class ProcessCfdiUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Parsing many XMLs can take a while; give it room but cap it.
    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public int $uploadId,
    ) {}

    public function handle(CfdiIngestService $ingest): void
    {
        $upload = CfdiUpload::with(['client', 'period'])->find($this->uploadId);

        if (! $upload || $upload->isFinished()) {
            return;
        }

        $upload->update(['status' => 'processing']);

        $absolute = Storage::disk('local')->path($upload->stored_path);

        try {
            $summary = $ingest->ingestFile($absolute, $upload->client, $upload->period);

            // Invoices may now be routed across several periods by their own
            // date. "Omitidas" (out-of-range) fold into the stored skipped count,
            // with their reasons appended to the errors list so the UI can show
            // why. by_period drives per-period follow-up below.
            $errors = array_merge($summary['errors'], $summary['omitida_reasons']);

            $upload->update([
                'status'       => 'done',
                'imported'     => $summary['imported'],
                'skipped'      => $summary['skipped'] + $summary['omitidas'],
                'failed'       => $summary['failed'],
                'errors'       => $errors,
                'processed_at' => now(),
            ]);

            // Advance and classify every period that received invoices, not just
            // the one that was active at upload time.
            $periodIds = array_keys($summary['by_period']);
            if (empty($periodIds) && $summary['imported'] > 0) {
                $periodIds = [$upload->period_id];   // safety fallback
            }

            foreach ($periodIds as $periodId) {
                $this->advancePeriodById($periodId);
                ClassifyPeriodInvoices::dispatch($periodId);
            }
        } catch (Throwable $e) {
            $upload->update([
                'status'       => 'failed',
                'fatal_error'  => $e->getMessage(),
                'processed_at' => now(),
            ]);

            throw $e; // surface to failed_jobs for visibility
        } finally {
            // Clean up the temp upload file regardless of outcome.
            Storage::disk('local')->delete($upload->stored_path);
        }
    }

    /**
     * Move a period forward only if it actually has invoices and hasn't already
     * progressed past the "downloaded" stage.
     */
    private function advancePeriodById(int $periodId): void
    {
        $period = \App\Models\Period::find($periodId);

        if ($period && $period->invoice_count > 0 && $period->status->step() < PeriodStatus::Downloaded->step()) {
            $period->update(['status' => PeriodStatus::Downloaded]);
        }
    }

    public function failed(Throwable $e): void
    {
        CfdiUpload::where('id', $this->uploadId)->update([
            'status'      => 'failed',
            'fatal_error' => $e->getMessage(),
        ]);
    }
}
