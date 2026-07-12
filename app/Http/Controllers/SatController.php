<?php

namespace App\Http\Controllers;

use App\Jobs\SubmitSatDownload;
use App\Models\Client;
use App\Models\SatCredential;
use App\Models\SatDownloadRequest;
use App\Services\Sat\SatCredentialService;
use App\Services\Sat\SatWebService;
use App\Services\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class SatController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
        private readonly SatCredentialService $credentials,
        private readonly SatWebService $sat,
    ) {}

    /** Overview: credential status per client + recent download requests. */
    public function index(): View
    {
        $clients = Client::active()
            ->with('satCredential')
            ->orderBy('razon_social')
            ->get();

        $requests = SatDownloadRequest::with('client')
            ->latest()
            ->limit(25)
            ->get();

        return view('sat.index', [
            'clients'  => $clients,
            'requests' => $requests,
            'pending'  => $requests->filter(fn ($r) => $r->isPending())->count(),
        ]);
    }

    /** Upload a client's e.firma (.cer + .key + password). */
    public function storeCredential(Request $request, Client $client): JsonResponse
    {
        $request->validate([
            'cer'      => ['required', 'file', 'max:10'],   // ~2KB in practice
            'key'      => ['required', 'file', 'max:10'],
            'password' => ['required', 'string'],
        ], [], [
            'cer' => 'certificado .cer',
            'key' => 'llave .key',
        ]);

        try {
            $credential = $this->credentials->store(
                $client,
                file_get_contents($request->file('cer')->getRealPath()),
                file_get_contents($request->file('key')->getRealPath()),
                $request->string('password')->toString(),
            );
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'e.firma registrada. Vigente hasta ' . $credential->valid_to?->format('d/m/Y') . '.',
        ]);
    }

    public function destroyCredential(Client $client): RedirectResponse
    {
        $this->credentials->forget($client);

        return back()->with('toast', ['type' => 'success', 'message' => 'e.firma eliminada.']);
    }

    /**
     * Request a month's CFDIs from SAT for a client.
     *
     * The (client, direction, type, exact period) tuple is unique in the DB
     * because asking SAT twice for the same period burns it permanently. A repeat
     * attempt is refused here with a clear explanation rather than a 500.
     */
    public function requestDownload(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'year'          => ['required', 'integer', 'min:2019', 'max:2100'],
            'month'         => ['required', 'integer', 'min:1', 'max:12'],
            'download_type' => ['required', 'in:issued,received'],
            'request_type'  => ['required', 'in:xml,metadata'],
        ]);

        if (! SatCredential::where('client_id', $client->id)->exists()) {
            return response()->json(['message' => 'Este cliente no tiene e.firma registrada.'], 422);
        }

        [$start, $end] = $this->sat->monthPeriod((int) $data['year'], (int) $data['month']);

        // Validate the period against SAT's v1.5 rules before touching the DB.
        try {
            $this->sat->assertPeriodValid($start, $end);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $existing = SatDownloadRequest::where('client_id', $client->id)
            ->where('download_type', $data['download_type'])
            ->where('request_type', $data['request_type'])
            ->where('period_start', $start)
            ->where('period_end', $end)
            ->first();

        // The current month's end is clamped to "now", so its timestamp differs on
        // every attempt and the unique index would not catch a repeat. Match on the
        // period *start* alone for the in-progress month.
        if (! $existing && $this->isCurrentMonth((int) $data['year'], (int) $data['month'])) {
            $existing = SatDownloadRequest::where('client_id', $client->id)
                ->where('download_type', $data['download_type'])
                ->where('request_type', $data['request_type'])
                ->where('period_start', $start)
                ->first();
        }

        if ($existing) {
            return response()->json([
                'message' => 'Ya existe una solicitud para este periodo (' . $existing->statusLabel() . '). '
                    . 'El SAT limita a dos solicitudes por periodo de por vida, así que no se reenvía.',
            ], 422);
        }

        // Attach to the matching accounting period, if it exists.
        $period = \App\Models\Period::firstOrCreate([
            'client_id' => $client->id,
            'year'      => (int) $data['year'],
            'month'     => (int) $data['month'],
        ]);

        $satRequest = SatDownloadRequest::create([
            'client_id'     => $client->id,
            'period_id'     => $period->id,
            'download_type' => $data['download_type'],
            'request_type'  => $data['request_type'],
            'period_start'  => $start,
            'period_end'    => $end,
            'status'        => 'solicitando',
        ]);

        SubmitSatDownload::dispatch($satRequest->id);

        return response()->json([
            'ok'         => true,
            'request_id' => $satRequest->id,
            'message'    => 'Solicitud enviada al SAT. La respuesta puede tardar de minutos a varias horas.',
        ]);
    }

    /** Poll a single request's status (the UI refreshes from this). */
    public function status(SatDownloadRequest $satRequest): JsonResponse
    {
        return response()->json([
            'status'   => $satRequest->status,
            'label'    => $satRequest->statusLabel(),
            'color'    => $satRequest->statusColor(),
            'cfdis'    => $satRequest->cfdi_count,
            'imported' => $satRequest->imported,
            'skipped'  => $satRequest->skipped,
            'error'    => $satRequest->error_message,
            'finished' => $satRequest->isFinished(),
        ]);
    }

    private function isCurrentMonth(int $year, int $month): bool
    {
        $now = now();

        return $year === $now->year && $month === $now->month;
    }
}
