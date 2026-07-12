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
 * Step 1: present the query to SAT. Fast (a single SOAP call), but it must not
 * block a web request, and it must never be retried blindly — a resubmitted
 * period is a permanently burned period ("5002 - solicitudes agotadas").
 * Hence tries = 1.
 */
class SubmitSatDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries = 1;   // never resubmit: SAT burns the period

    public function __construct(public int $requestId) {}

    public function handle(SatDownloadService $downloads): void
    {
        $request = SatDownloadRequest::with('client')->find($this->requestId);

        if (! $request || $request->status !== 'solicitando') {
            return;
        }

        $downloads->submit($request);
    }

    public function failed(Throwable $e): void
    {
        SatDownloadRequest::where('id', $this->requestId)->update([
            'status'        => 'error',
            'error_message' => $e->getMessage(),
            'completed_at'  => now(),
        ]);
    }
}
