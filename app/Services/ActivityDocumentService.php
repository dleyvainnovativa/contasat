<?php

namespace App\Services;

use App\Models\ActivityDocument;
use App\Models\Client;
use App\Models\Period;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Handles the upload-type Calendario activities: store the PDF, extract its text
 * (pdftotext), and validate.
 *
 * Validation for this round is RFC-only: the client's exact RFC must appear in
 * the document text. For 32D we also detect the opinion sense (positiva /
 * negativa). Period validation is deliberately not done yet — see the roadmap.
 *
 * On a mismatch the upload is rejected (the row is NOT marked realizada); the
 * stored record keeps the reason so the accountant sees why.
 */
class ActivityDocumentService
{
    /** Activities that accept an uploaded, RFC-validated PDF. */
    public const UPLOAD_ACTIVITIES = [
        'constancia',
        'op_32d',
        'presentacion_dyp',
        'pago_declaracion',
        'diot',
        'econtabilidad',
    ];

    public function isUploadActivity(string $activityKey): bool
    {
        return in_array($activityKey, self::UPLOAD_ACTIVITIES, true);
    }

    /**
     * Store + validate an uploaded document. Throws (rejects) when the client's
     * RFC isn't found in the text. Returns the persisted ActivityDocument.
     */
    public function handleUpload(Client $client, Period $period, string $activityKey, UploadedFile $file): ActivityDocument
    {
        if (! $this->isUploadActivity($activityKey)) {
            throw new RuntimeException('Esta actividad no admite carga de archivos.');
        }
        if (strtolower($file->getClientOriginalExtension()) !== 'pdf') {
            throw new RuntimeException('El archivo debe ser un PDF.');
        }

        // Store first (we keep the file regardless, per the design), then extract.
        $dir  = "activity-docs/{$client->id}/{$period->id}";
        $path = $file->store($dir);

        try {
            $text = $this->extractText(Storage::path($path));
        } catch (\Throwable $e) {
            Storage::delete($path);
            throw new RuntimeException('No se pudo leer el PDF (¿es una imagen escaneada sin texto?).', previous: $e);
        }

        // RFC validation: the client's exact RFC must appear in the text
        // (case-insensitive, tolerant of surrounding whitespace).
        $rfcOk = $this->textContainsRfc($text, $client->rfc);

        // 32D: detect the opinion sense.
        $sentido = $activityKey === 'op_32d' ? $this->detect32dSentido($text) : null;

        if (! $rfcOk) {
            // Rejected: keep nothing (don't leave an orphan file for a bad upload).
            Storage::delete($path);
            throw new RuntimeException(
                "El RFC del cliente ({$client->rfc}) no aparece en el documento. "
                    . 'Verifica que el archivo corresponda a este cliente.'
            );
        }

        $detalle = match (true) {
            $activityKey === 'op_32d' && $sentido === 'positiva' => 'Opinión positiva.',
            $activityKey === 'op_32d' && $sentido === 'negativa' => 'Opinión negativa.',
            $activityKey === 'op_32d'                            => 'RFC validado; no se detectó el sentido (positiva/negativa).',
            default                                              => 'RFC validado correctamente.',
        };

        // Replace any prior document for this activity (keep the latest only).
        $existing = ActivityDocument::where('client_id', $client->id)
            ->where('period_id', $period->id)
            ->where('activity_key', $activityKey)
            ->first();

        if ($existing) {
            Storage::delete($existing->path);
            $existing->update([
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'rfc_ok'        => true,
                'sentido'       => $sentido,
                'detalle'       => $detalle,
                'uploaded_by'   => Auth::id(),
            ]);
            return $existing->fresh();
        }

        return ActivityDocument::create([
            'client_id'     => $client->id,
            'period_id'     => $period->id,
            'activity_key'  => $activityKey,
            'path'          => $path,
            'original_name' => $file->getClientOriginalName(),
            'rfc_ok'        => true,
            'sentido'       => $sentido,
            'detalle'       => $detalle,
            'uploaded_by'   => Auth::id(),
        ]);
    }

    /** Normalize both sides and check the client RFC appears in the doc text. */
    private function textContainsRfc(string $text, string $rfc): bool
    {
        $needle = strtoupper(preg_replace('/\s+/', '', $rfc));
        if ($needle === '') {
            return false;
        }
        // Collapse whitespace in the haystack too — SAT PDFs sometimes split the
        // RFC across spaces/columns in the extracted layout.
        $haystack = strtoupper(preg_replace('/\s+/', '', $text));

        return str_contains($haystack, $needle);
    }

    /**
     * 32D opinion sense. The SAT "Opinión del cumplimiento" PDF states the sense
     * explicitly; we look for the words, preferring the more specific negative.
     */
    private function detect32dSentido(string $text): ?string
    {
        $t = mb_strtolower($text);

        if (str_contains($t, 'negativa')) {
            return 'negativa';
        }
        if (str_contains($t, 'positiva')) {
            return 'positiva';
        }

        return null;
    }

    private function extractText(string $absolutePath): string
    {
        return \Spatie\PdfToText\Pdf::getText(
            $absolutePath,
            config('services.pdftotext.path'),
            ['layout'],
        );
    }
}
