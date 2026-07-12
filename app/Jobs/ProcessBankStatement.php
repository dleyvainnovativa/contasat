<?php

namespace App\Jobs;

use App\Enums\PeriodStatus;
use App\Models\BankStatement;
use App\Services\BankStatementExtractor;
use App\Services\StatementPersistService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Extracts a bank statement PDF off the request cycle. On Hostinger this runs on
 * the database queue drained by cron. The AI call plus PDF parsing can take a
 * while, so the UI polls for status (mirrors the CFDI upload flow).
 */
class ProcessBankStatement implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(
        public int $statementId,
    ) {}

    public function handle(BankStatementExtractor $extractor, StatementPersistService $persist): void
    {
        $statement = BankStatement::with('period')->find($this->statementId);

        if (! $statement) {
            return;
        }

        $statement->update(['extraccion_status' => 'procesando']);

        $absolute = Storage::disk('local')->path($statement->pdf_path);

        try {
            $result = $extractor->extract($absolute);
            $persist->persist($statement, $result);

            // Advance the period to "Extracted" if it's behind that stage.
            $this->advancePeriod($statement);
        } catch (Throwable $e) {
            $statement->update([
                'extraccion_status' => 'error',
                'extraccion_error'  => $e->getMessage(),
            ]);

            throw $e; // visible in failed_jobs
        }
    }

    private function advancePeriod(BankStatement $statement): void
    {
        $period = $statement->period?->fresh();

        if ($period && $period->status->step() < PeriodStatus::Extracted->step()) {
            $period->update(['status' => PeriodStatus::Extracted]);
        }
    }

    public function failed(Throwable $e): void
    {
        BankStatement::where('id', $this->statementId)->update([
            'extraccion_status' => 'error',
            'extraccion_error'  => $e->getMessage(),
        ]);
    }
}
