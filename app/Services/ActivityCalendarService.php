<?php

namespace App\Services;

use App\Models\ActivityStatus;
use App\Models\CfdiUpload;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Period;
use Illuminate\Support\Collection;

/**
 * Builds the Calendario de actividades board for a client + period.
 *
 * For each of the 11 activities it returns a resolved status by combining:
 *   1. the stored manual tag / "No aplica" toggle (activity_statuses), and
 *   2. an auto-detected status for the three detectable activities.
 *
 * Resolution order (see also the migration):
 *   enabled === false  -> no_aplica
 *   manual_status set   -> manual_status
 *   auto-detectable     -> auto_status
 *   otherwise           -> pendiente
 *
 * Auto-detected activities: descarga_xml, clasificacion_xml, conciliacion.
 * All others are manual-only and fall through to pendiente until tagged.
 */
class ActivityCalendarService
{
    /**
     * @return Collection<int,array{
     *   key:string, label:string, mode:string, group:?string,
     *   status:string, enabled:bool, deep_link:?string, sat_url:?string
     * }>
     */
    public function resolve(Client $client, Period $period): Collection
    {
        // Stored manual rows for this client/period, keyed by activity_key.
        $stored = ActivityStatus::where('client_id', $client->id)
            ->where('period_id', $period->id)
            ->get()
            ->keyBy('activity_key');

        // Uploaded documents for upload-type activities, keyed by activity_key.
        $docs = \App\Models\ActivityDocument::where('client_id', $client->id)
            ->where('period_id', $period->id)
            ->get()
            ->keyBy('activity_key');

        $auto = $this->autoStatuses($period);

        return collect(ActivityStatus::ACTIVITIES)->map(function ($meta, $key) use ($stored, $auto, $docs) {
            /** @var ActivityStatus|null $row */
            $row = $stored->get($key);
            $enabled = $row?->enabled ?? true;
            $doc = $docs->get($key);

            $status = self::resolveStatus($meta, $row, $doc, $auto[$key] ?? null);

            return [
                'key'       => $key,
                'label'     => $meta['label'],
                'mode'      => $meta['mode'],
                'group'     => $meta['group'],
                'status'    => $status,
                'enabled'   => $enabled,
                'deep_link' => $status === ActivityStatus::STATUS_EN_PROCESO
                    ? $this->deepLink($key)
                    : null,
                'sat_url'   => $meta['sat'] ?? null,
                'document'  => $doc ? [
                    'name'    => $doc->original_name,
                    'sentido' => $doc->sentido,
                    'detalle' => $doc->detalle,
                    'sent_at' => $doc->sent_at?->format('Y-m-d H:i'),
                    'sent_to' => $doc->sent_to,
                    'at'      => $doc->updated_at?->format('Y-m-d H:i'),
                ] : null,
            ];
        })->values();
    }

    /**
     * Resolve one activity's status from its stored row, document, and (for auto
     * activities) precomputed auto status. Single source of truth for the
     * resolution ladder — used by resolve() and by the dashboard's batch matrix
     * (DashboardService), so the two can never drift.
     *
     * Ladder: disabled → no_aplica; manual tag wins; upload → doc RFC-ok;
     * email → doc sent; auto → its computed status; otherwise pendiente.
     */
    public static function resolveStatus(array $meta, ?ActivityStatus $row, ?\App\Models\ActivityDocument $doc, ?string $autoStatus): string
    {
        $enabled = $row?->enabled ?? true;

        if (! $enabled) {
            return ActivityStatus::STATUS_NO_APLICA;
        }
        if ($row && $row->manual_status) {
            return $row->manual_status;
        }
        if ($meta['mode'] === 'upload') {
            return $doc && $doc->rfc_ok ? ActivityStatus::STATUS_REALIZADA : ActivityStatus::STATUS_PENDIENTE;
        }
        if ($meta['mode'] === 'email') {
            return $doc && $doc->sent_at ? ActivityStatus::STATUS_REALIZADA : ActivityStatus::STATUS_PENDIENTE;
        }
        if ($autoStatus !== null) {
            return $autoStatus;
        }

        return ActivityStatus::STATUS_PENDIENTE;
    }

    /**
     * Compute the three auto-detectable statuses from existing data.
     *
     * @return array<string,string> activity_key => status
     */
    private function autoStatuses(Period $period): array
    {
        $uploads  = CfdiUpload::where('period_id', $period->id);
        $imported = (clone $uploads)->where('imported', '>', 0)->count();

        $total        = Invoice::where('period_id', $period->id)->count();
        $sinClasificar = $total === 0 ? 0 : Invoice::where('period_id', $period->id)
            ->where('clasificacion', 'sin_clasificar')->count();

        return [
            'descarga_xml'      => self::descargaStatusFrom((clone $uploads)->count(), $imported),
            'clasificacion_xml' => self::clasificacionStatusFrom($total, $sinClasificar),
            'conciliacion'      => self::conciliacionStatusFrom((int) $period->movement_count, (int) $period->unmatched_count),
        ];
    }

    /** Realizada if any CFDI upload imported rows; en_proceso if uploaded but nothing imported; else pendiente. */
    public static function descargaStatusFrom(int $uploadCount, int $importedUploadCount): string
    {
        if ($importedUploadCount > 0) {
            return ActivityStatus::STATUS_REALIZADA;
        }
        if ($uploadCount > 0) {
            return ActivityStatus::STATUS_EN_PROCESO;
        }

        return ActivityStatus::STATUS_PENDIENTE;
    }

    /** Realizada when every invoice is classified; en_proceso while some remain; pendiente with no invoices. */
    public static function clasificacionStatusFrom(int $total, int $sinClasificar): string
    {
        if ($total === 0) {
            return ActivityStatus::STATUS_PENDIENTE;
        }

        return $sinClasificar === 0
            ? ActivityStatus::STATUS_REALIZADA
            : ActivityStatus::STATUS_EN_PROCESO;
    }

    /** Realizada when no movements remain unmatched; en_proceso while some do; pendiente with no movements. */
    public static function conciliacionStatusFrom(int $movements, int $unmatched): string
    {
        if ($movements === 0) {
            return ActivityStatus::STATUS_PENDIENTE;
        }

        return $unmatched === 0
            ? ActivityStatus::STATUS_REALIZADA
            : ActivityStatus::STATUS_EN_PROCESO;
    }

    /** Where an "en proceso" auto activity sends the accountant. Unfiltered landing pages. */
    private function deepLink(string $key): ?string
    {
        return match ($key) {
            'clasificacion_xml' => route('invoices.view', 'gasto'),
            'conciliacion'      => route('reconciliation.index'),
            'descarga_xml'      => route('sat.index'),
            default             => null,
        };
    }
}
