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

        $auto = $this->autoStatuses($period);

        return collect(ActivityStatus::ACTIVITIES)->map(function ($meta, $key) use ($stored, $auto) {
            /** @var ActivityStatus|null $row */
            $row = $stored->get($key);
            $enabled = $row?->enabled ?? true;

            if (! $enabled) {
                $status = ActivityStatus::STATUS_NO_APLICA;
            } elseif ($row && $row->manual_status) {
                $status = $row->manual_status;
            } elseif (isset($auto[$key])) {
                $status = $auto[$key];
            } else {
                $status = ActivityStatus::STATUS_PENDIENTE;
            }

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
            ];
        })->values();
    }

    /**
     * Compute the three auto-detectable statuses from existing data.
     *
     * @return array<string,string> activity_key => status
     */
    private function autoStatuses(Period $period): array
    {
        return [
            'descarga_xml'      => $this->descargaXmlStatus($period),
            'clasificacion_xml' => $this->clasificacionStatus($period),
            'conciliacion'      => $this->conciliacionStatus($period),
        ];
    }

    /** Realizada if any CFDI upload actually imported rows; en_proceso if uploaded but nothing imported yet. */
    private function descargaXmlStatus(Period $period): string
    {
        $uploads = CfdiUpload::where('period_id', $period->id);

        if ((clone $uploads)->where('imported', '>', 0)->exists()) {
            return ActivityStatus::STATUS_REALIZADA;
        }
        if ((clone $uploads)->exists()) {
            return ActivityStatus::STATUS_EN_PROCESO;
        }

        return ActivityStatus::STATUS_PENDIENTE;
    }

    /** Realizada when every invoice in the period is classified; en_proceso while some remain. */
    private function clasificacionStatus(Period $period): string
    {
        $total = Invoice::where('period_id', $period->id)->count();
        if ($total === 0) {
            return ActivityStatus::STATUS_PENDIENTE;
        }

        $sinClasificar = Invoice::where('period_id', $period->id)
            ->where('clasificacion', 'sin_clasificar')
            ->count();

        if ($sinClasificar === 0) {
            return ActivityStatus::STATUS_REALIZADA;
        }

        // Some (or all) invoices remain unclassified → in progress, matching the
        // spec's "dirige al usuario al apartado donde está el pendiente".
        return ActivityStatus::STATUS_EN_PROCESO;
    }

    /** Uses the period's cached reconciliation counters. */
    private function conciliacionStatus(Period $period): string
    {
        $movements = (int) $period->movement_count;
        $unmatched = (int) $period->unmatched_count;

        if ($movements === 0) {
            return ActivityStatus::STATUS_PENDIENTE;
        }
        if ($unmatched === 0) {
            return ActivityStatus::STATUS_REALIZADA;
        }

        // Movements exist with some still unmatched → in progress.
        return ActivityStatus::STATUS_EN_PROCESO;
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
