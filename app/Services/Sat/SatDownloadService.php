<?php

namespace App\Services\Sat;

use App\Models\Period;
use App\Models\SatDownloadRequest;
use App\Services\CfdiIngestService;
use Illuminate\Support\Facades\Storage;
use PhpCfdi\SatWsDescargaMasiva\PackageReader\CfdiPackageReader;
use RuntimeException;
use Throwable;

/**
 * Drives a SAT descarga masiva through its four steps.
 *
 * SAT's own analogy: three windows. At the first you file a request and get a
 * receipt — that does not mean your data is ready. At the second you keep asking
 * whether it's ready. At the third you collect the boxes, one at a time.
 *
 * Timing is the thing to internalize: verification can take minutes, and in rare
 * cases up to 72 hours. Nothing here blocks; each step is called from a queued
 * job or the scheduled poller.
 *
 * Requests are never retried against SAT with the same period, because asking
 * twice for the same period returns "5002 - Se han agotado las solicitudes de
 * por vida" and burns that period permanently. The DB's unique index is the
 * primary guard; this service refuses at the application layer too.
 */
class SatDownloadService
{
    public function __construct(
        private readonly SatWebService $sat,
        private readonly CfdiIngestService $ingest,
    ) {}

    /**
     * Step 1 — submit the query. Records the SAT request id for later polling.
     */
    public function submit(SatDownloadRequest $request): void
    {
        $client = $request->client;

        $service = $this->sat->serviceFor($client);
        $query = $this->sat->buildQuery(
            $request->period_start,
            $request->period_end,
            $request->download_type,
            $request->request_type,
        );

        $result = $service->query($query);
        $status = $result->getStatus();

        if (! $status->isAccepted()) {
            $this->fail($request, "SAT rechazó la solicitud: {$status->getMessage()}", $status->getCode());

            return;
        }

        $request->update([
            'sat_request_id'     => $result->getRequestId(),
            'status'             => 'verificando',
            'sat_status_code'    => (string) $status->getCode(),
            'sat_status_message' => $status->getMessage(),
        ]);
    }

    /**
     * Step 2 — ask SAT whether the packages are ready. Called repeatedly by the
     * scheduler until the request finishes, fails, or expires.
     *
     * @return bool true when the request reached a terminal state.
     */
    public function verify(SatDownloadRequest $request): bool
    {
        if (! $request->sat_request_id) {
            $this->fail($request, 'No hay identificador de solicitud del SAT.');

            return true;
        }

        $service = $this->sat->serviceFor($request->client);
        $verify = $service->verify($request->sat_request_id);

        $request->increment('verify_attempts');
        $request->update(['last_verified_at' => now()]);

        // Was the verification call itself accepted?
        if (! $verify->getStatus()->isAccepted()) {
            $this->fail($request, "Fallo al verificar: {$verify->getStatus()->getMessage()}");

            return true;
        }

        // Was the original request rejected outright?
        if (! $verify->getCodeRequest()->isAccepted()) {
            $this->fail(
                $request,
                "El SAT rechazó la solicitud: {$verify->getCodeRequest()->getMessage()}",
                (string) $verify->getCodeRequest()->getValue(),
            );

            return true;
        }

        $statusRequest = $verify->getStatusRequest();

        if ($statusRequest->isExpired() || $statusRequest->isFailure() || $statusRequest->isRejected()) {
            $this->fail($request, 'La solicitud no se puede completar (expirada, fallida o rechazada).');

            return true;
        }

        // Still cooking — come back later.
        if ($statusRequest->isInProgress() || $statusRequest->isAccepted()) {
            return false;
        }

        if ($statusRequest->isFinished()) {
            $request->update([
                'status'      => 'descargando',
                'package_ids' => $verify->getPackagesIds(),
                'cfdi_count'  => $verify->getNumberCfdis(),
            ]);

            return false; // ready to download, not terminal yet
        }

        return false;
    }

    /**
     * Step 3 + 4 — download every package and feed the CFDIs into the existing
     * ingest pipeline. A request generates one *or several* packages; all of them
     * are needed for the data to be complete.
     */
    public function downloadAndIngest(SatDownloadRequest $request): void
    {
        $packageIds = $request->package_ids ?? [];

        if ($packageIds === []) {
            $this->fail($request, 'La verificación no devolvió paquetes.');

            return;
        }

        // Metadata packages carry no XML, so there is nothing to ingest.
        if ($request->request_type === 'metadata') {
            $request->update(['status' => 'completado', 'completed_at' => now()]);

            return;
        }

        $service = $this->sat->serviceFor($request->client);
        $period = $request->period;

        $imported = 0;
        $skipped = 0;
        $downloaded = 0;

        foreach ($packageIds as $packageId) {
            $download = $service->download($packageId);

            if (! $download->getStatus()->isAccepted()) {
                // One bad package shouldn't discard the others already fetched.
                continue;
            }

            $zipPath = $this->storePackage($request, $packageId, $download->getPackageContent());
            $downloaded++;

            $result = $this->ingestPackage($zipPath, $request, $period);
            $imported += $result['imported'];
            $skipped += $result['skipped'];

            Storage::disk('local')->delete($this->relativePath($request, $packageId));
        }

        if ($downloaded === 0) {
            $this->fail($request, 'No se pudo descargar ningún paquete.');

            return;
        }

        $request->update([
            'status'              => 'completado',
            'packages_downloaded' => $downloaded,
            'imported'            => $imported,
            'skipped'             => $skipped,
            'completed_at'        => now(),
        ]);
    }

    /**
     * Read a CFDI package and hand each XML to the existing Phase 1 ingest.
     * Reusing CfdiIngestService means SAT-sourced and hand-uploaded invoices go
     * through exactly the same parsing, dedup, and persistence path.
     *
     * @return array{imported:int, skipped:int}
     */
    private function ingestPackage(string $absoluteZipPath, SatDownloadRequest $request, ?Period $period): array
    {
        if (! $period) {
            return ['imported' => 0, 'skipped' => 0];
        }

        try {
            $reader = CfdiPackageReader::createFromFile($absoluteZipPath);
        } catch (Throwable $e) {
            return ['imported' => 0, 'skipped' => 0];
        }

        // Unpack the CFDIs into a plain ZIP the existing ingest already accepts.
        $staged = $this->stageAsPlainZip($reader, $request);

        try {
            $summary = $this->ingest->ingestFile($staged, $request->client, $period);

            return ['imported' => $summary['imported'], 'skipped' => $summary['skipped']];
        } catch (Throwable) {
            return ['imported' => 0, 'skipped' => 0];
        } finally {
            @unlink($staged);
        }
    }

    /** Repack the reader's CFDIs into a flat ZIP for CfdiIngestService. */
    private function stageAsPlainZip(CfdiPackageReader $reader, SatDownloadRequest $request): string
    {
        $path = storage_path('app/tmp/sat_staged_' . $request->id . '_' . uniqid() . '.zip');
        @mkdir(dirname($path), 0775, true);

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($reader->cfdis() as $uuid => $content) {
            $zip->addFromString("{$uuid}.xml", $content);
        }
        $zip->close();

        return $path;
    }

    private function storePackage(SatDownloadRequest $request, string $packageId, string $contents): string
    {
        $relative = $this->relativePath($request, $packageId);
        Storage::disk('local')->put($relative, $contents);

        return Storage::disk('local')->path($relative);
    }

    private function relativePath(SatDownloadRequest $request, string $packageId): string
    {
        return "sat-packages/{$request->client_id}/{$packageId}.zip";
    }

    private function fail(SatDownloadRequest $request, string $message, ?string $code = null): void
    {
        $request->update([
            'status'          => 'error',
            'error_message'   => $message,
            'sat_status_code' => $code,
            'completed_at'    => now(),
        ]);
    }
}
