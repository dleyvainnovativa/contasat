<?php

namespace App\Http\Controllers;

use App\Models\ActivityStatus;
use App\Services\ActivityCalendarService;
use App\Services\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Calendario de actividades — a per-client, per-period semáforo of the ~11
 * monthly fiscal activities. Reads the active client + period from WorkContext
 * (session-backed, same as every other client/period screen).
 */
class ActivityCalendarController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
        private readonly ActivityCalendarService $service,
        private readonly \App\Services\ActivityDocumentService $documents,
        private readonly \App\Services\ActivityEmailService $emails,
    ) {}

    public function index(): View|RedirectResponse
    {
        if (! $this->context->hasPeriod()) {
            return redirect()->route('dashboard')
                ->with('toast', ['type' => 'warning', 'message' => 'Selecciona un cliente y periodo primero.']);
        }

        $client = $this->context->client();
        $period = $this->context->period();

        $activities = $this->service->resolve($client, $period);
        $done = $activities->where('status', ActivityStatus::STATUS_REALIZADA)->count();

        return view('calendario.index', [
            'client'     => $client,
            'period'     => $period,
            'activities' => $activities,
            'doneCount'  => $done,
            'totalCount' => $activities->count(),
        ]);
    }

    /** Set (or clear) the manual status tag for one activity. */
    public function updateStatus(Request $request, string $activityKey): JsonResponse
    {
        $this->assertContext();

        if (! ActivityStatus::isValidKey($activityKey)) {
            return response()->json(['message' => 'Actividad desconocida.'], 422);
        }

        $data = $request->validate([
            // null clears the manual tag and lets the activity fall back to auto.
            'manual_status' => ['nullable', 'in:' . implode(',', ActivityStatus::MANUAL_STATUSES)],
        ]);

        $row = $this->upsert($activityKey);
        $row->manual_status = $data['manual_status'] ?? null;
        $row->updated_by = Auth::id();
        $row->save();

        return response()->json(['message' => 'Estado actualizado.']);
    }

    /** Flip the "No aplica" toggle (enabled = false → No aplica). */
    public function toggleEnabled(Request $request, string $activityKey): JsonResponse
    {
        $this->assertContext();

        if (! ActivityStatus::isValidKey($activityKey)) {
            return response()->json(['message' => 'Actividad desconocida.'], 422);
        }

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $row = $this->upsert($activityKey);
        $row->enabled = $data['enabled'];
        $row->updated_by = Auth::id();
        $row->save();

        return response()->json([
            'message' => $data['enabled'] ? 'Actividad habilitada.' : 'Actividad marcada como No aplica.',
        ]);
    }

    /** Upload + RFC-validate a PDF for an upload-type activity. */
    public function upload(Request $request, string $activityKey): JsonResponse
    {
        $this->assertContext();

        if (! ActivityStatus::isValidKey($activityKey) || ! $this->documents->isUploadActivity($activityKey)) {
            return response()->json(['message' => 'Esta actividad no admite carga de archivos.'], 422);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'], // 20 MB
        ]);

        try {
            $doc = $this->documents->handleUpload(
                $this->context->client(),
                $this->context->period(),
                $activityKey,
                $request->file('file'),
            );
        } catch (\Throwable $e) {
            // Rejected (RFC mismatch, unreadable PDF, etc.) — surface the reason.
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok'      => true,
            'message' => $doc->detalle ?: 'Documento validado.',
            'sentido' => $doc->sentido,
        ]);
    }

    /** Prefilled, editable body for the Solicitud email (shown in the confirm modal). */
    public function solicitudForm(): JsonResponse
    {
        $this->assertContext();
        $client = $this->context->client();

        return response()->json([
            'to'    => $client->email,
            'body'  => $this->emails->defaultSolicitudBody($client, $this->context->period()),
            'has_email' => (bool) trim((string) $client->email),
        ]);
    }

    /** Send the Solicitud email with the (possibly edited) body. */
    public function sendSolicitud(Request $request): JsonResponse
    {
        $this->assertContext();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $this->emails->sendSolicitud($this->context->client(), $this->context->period(), $data['body']);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Solicitud enviada al cliente.']);
    }

    /** Download the Expediente PDF for preview (before sending). */
    public function expedientePreview()
    {
        $this->assertContext();

        [$bytes, $name] = $this->emails->buildExpedientePdf($this->context->client(), $this->context->period());

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $name . '"',
        ]);
    }

    /** Generate + email the Expediente Fiscal to the client. */
    public function sendExpediente(): JsonResponse
    {
        $this->assertContext();

        try {
            $this->emails->sendExpediente($this->context->client(), $this->context->period());
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Expediente generado y enviado al cliente.']);
    }

    /** Find or create the row for the active client/period + given activity. */
    private function upsert(string $activityKey): ActivityStatus
    {
        return ActivityStatus::firstOrNew([
            'client_id'    => $this->context->client()->id,
            'period_id'    => $this->context->period()->id,
            'activity_key' => $activityKey,
        ]);
    }

    private function assertContext(): void
    {
        abort_unless($this->context->hasPeriod(), 409, 'Selecciona un cliente y periodo primero.');
    }
}
