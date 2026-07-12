<?php

namespace App\Jobs;

use App\Models\SatDownloadRequest;
use App\Services\Sat\SatDownloadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Steps 2–4, run on a schedule.
 *
 * SAT gives no guaranteed turnaround: verification typically resolves in minutes,
 * but larger periods can take hours, and rare cases have run to 72 hours. So this
 * polls patiently rather than waiting on a thread.
 *
 * Backoff: don't hammer SAT. We only verify a request whose last check is older
 * than a growing interval — a few minutes early on, widening toward an hour.
 * A request that has been pending beyond the cutoff is abandoned as expired.
 */
class PollSatDownloads implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;   // downloads can be large
    public int $tries = 1;

    /** Give up on a request that SAT never completes. */
    private const MAX_AGE_HOURS = 96;

    public function handle(SatDownloadService $downloads): void
    {
        $pending = SatDownloadRequest::with('client', 'period')
            ->whereIn('status', ['verificando', 'descargando'])
            ->get();

        foreach ($pending as $request) {
            try {
                $this->processOne($request, $downloads);
            } catch (Throwable $e) {
                // One client's failure must not stall the other 49.
                $request->update([
                    'status'        => 'error',
                    'error_message' => $e->getMessage(),
                    'completed_at'  => now(),
                ]);
            }
        }
    }

    private function processOne(SatDownloadRequest $request, SatDownloadService $downloads): void
    {
        if ($request->created_at->diffInHours(now()) > self::MAX_AGE_HOURS) {
            $request->update([
                'status'        => 'error',
                'error_message' => 'El SAT no completó la solicitud en 96 horas.',
                'completed_at'  => now(),
            ]);

            return;
        }

        if ($request->status === 'verificando') {
            if (! $this->dueForCheck($request)) {
                return;
            }

            $downloads->verify($request);
            $request->refresh();
        }

        // verify() promotes to 'descargando' once SAT reports the packages ready.
        if ($request->status === 'descargando') {
            $downloads->downloadAndIngest($request);
        }
    }

    /**
     * Widening backoff: 2 min for the first few checks, then 10, then 30.
     * Keeps us polite without leaving a finished package sitting for an hour.
     */
    private function dueForCheck(SatDownloadRequest $request): bool
    {
        if (! $request->last_verified_at) {
            return true;
        }

        $minutes = match (true) {
            $request->verify_attempts < 5  => 2,
            $request->verify_attempts < 15 => 10,
            default                        => 30,
        };

        return $request->last_verified_at->addMinutes($minutes)->isPast();
    }
}
