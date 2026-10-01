<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Models\ActivityDocument;
use App\Models\ActivityStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\CfdiUpload;
use App\Models\Period;
use Illuminate\Support\Collection;

/**
 * Builds the multi-client period dashboard. At 50+ clients the accountant works
 * the exception queue, not one client at a time — so this assembles a single
 * overview of every active client for a given month.
 *
 * Each row now carries the full Calendario de Actividades semáforo (11 activity
 * statuses) instead of raw invoice/movement counts. To keep this affordable on
 * shared hosting, the activity matrix is resolved in a handful of grouped
 * queries for the whole month (see overview()), not one ActivityCalendarService
 * call per client — the resolution logic itself is shared with that service via
 * its static helpers so the two can never drift.
 */
class DashboardService
{
    /**
     * One row per active client for the given month: its period status, the
     * resolved status of each of the 11 activities, and a progress percentage
     * derived from how many of those activities are "realizada".
     */
    public function overview(int $year, int $month): Collection
    {
        $clients = Client::active()->orderBy('razon_social')->get();

        $periods = Period::where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('client_id');

        $periodIds = $periods->pluck('id')->all();

        // --- Batch prefetch, keyed by period_id --------------------------------
        $storedByPeriod = ActivityStatus::whereIn('period_id', $periodIds)->get()->groupBy('period_id');
        $docsByPeriod   = ActivityDocument::whereIn('period_id', $periodIds)->get()->groupBy('period_id');

        // Auto-status inputs as per-period aggregates (one query each). Boolean
        // sums (expr > 0 / = value) yield 1/0 in both SQLite and MySQL.
        $uploadAgg = CfdiUpload::selectRaw('period_id, count(*) as total, sum(imported > 0) as imported_uploads')
            ->whereIn('period_id', $periodIds)->groupBy('period_id')->get()->keyBy('period_id');

        $invoiceAgg = Invoice::selectRaw("period_id, count(*) as total, sum(clasificacion = 'sin_clasificar') as sin_clasificar")
            ->whereIn('period_id', $periodIds)->groupBy('period_id')->get()->keyBy('period_id');

        $activities = collect(ActivityStatus::ACTIVITIES);
        $total = $activities->count();

        return $clients->map(function (Client $client) use (
            $periods, $storedByPeriod, $docsByPeriod, $uploadAgg, $invoiceAgg, $activities, $total, $year, $month
        ) {
            $period = $periods->get($client->id);
            $status = $period?->status ?? PeriodStatus::NotStarted;

            $acts = [];
            $done = 0;

            if ($period) {
                $stored = ($storedByPeriod->get($period->id) ?? collect())->keyBy('activity_key');
                $docs   = ($docsByPeriod->get($period->id) ?? collect())->keyBy('activity_key');
                $ua     = $uploadAgg->get($period->id);
                $ia     = $invoiceAgg->get($period->id);

                $auto = [
                    'descarga_xml'      => ActivityCalendarService::descargaStatusFrom((int) ($ua->total ?? 0), (int) ($ua->imported_uploads ?? 0)),
                    'clasificacion_xml' => ActivityCalendarService::clasificacionStatusFrom((int) ($ia->total ?? 0), (int) ($ia->sin_clasificar ?? 0)),
                    'conciliacion'      => ActivityCalendarService::conciliacionStatusFrom((int) $period->movement_count, (int) $period->unmatched_count),
                ];

                foreach ($activities as $key => $meta) {
                    $st = ActivityCalendarService::resolveStatus($meta, $stored->get($key), $docs->get($key), $auto[$key] ?? null);
                    $acts[] = ['key' => $key, 'label' => $meta['label'], 'status' => $st];
                    if ($st === ActivityStatus::STATUS_REALIZADA) {
                        $done++;
                    }
                }
            } else {
                // No period row yet — everything is pending.
                foreach ($activities as $key => $meta) {
                    $acts[] = ['key' => $key, 'label' => $meta['label'], 'status' => ActivityStatus::STATUS_PENDIENTE];
                }
            }

            return [
                'client'           => $client,
                'period'           => $period,
                'status'           => $status,
                'year'             => $year,
                'month'            => $month,
                'activities'       => $acts,
                'activities_done'  => $done,
                'activities_total' => $total,
                'progress'         => $total > 0 ? (int) round($done / $total * 100) : 0,
            ];
        });
    }

    /**
     * Counts per period status for the summary cards. Takes the already-built
     * overview collection so the dashboard resolves everything in one pass.
     */
    public function statusTotals(Collection $overview): array
    {
        $totals = collect(PeriodStatus::cases())
            ->mapWithKeys(fn (PeriodStatus $s) => [$s->value => 0])
            ->all();

        foreach ($overview as $row) {
            $totals[$row['status']->value]++;
        }

        return $totals;
    }
}
