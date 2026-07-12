<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Models\Client;
use App\Models\Period;
use Illuminate\Support\Collection;

/**
 * Builds the multi-client period dashboard. At 50+ clients the accountant works
 * the exception queue, not one client at a time — so this assembles a single
 * overview of every active client's status for a given month.
 */
class DashboardService
{
    /**
     * One row per active client for the given month, with its period status
     * (or "not started" if no period row exists yet).
     */
    public function overview(int $year, int $month): Collection
    {
        $clients = Client::active()->orderBy('razon_social')->get();

        $periods = Period::where('year', $year)
            ->where('month', $month)
            ->get()
            ->keyBy('client_id');

        return $clients->map(function (Client $client) use ($periods, $year, $month) {
            $period = $periods->get($client->id);
            $status = $period?->status ?? PeriodStatus::NotStarted;

            return [
                'client'   => $client,
                'period'   => $period,
                'status'   => $status,
                'year'     => $year,
                'month'    => $month,
                'invoices' => $period?->invoice_count ?? 0,
                'movements'=> $period?->movement_count ?? 0,
                'matched'  => $period?->matched_count ?? 0,
                'progress' => $period?->progress ?? 0,
            ];
        });
    }

    /** Counts per status for the summary cards at the top of the dashboard. */
    public function statusTotals(int $year, int $month): array
    {
        $overview = $this->overview($year, $month);

        $totals = collect(PeriodStatus::cases())
            ->mapWithKeys(fn (PeriodStatus $s) => [$s->value => 0])
            ->all();

        foreach ($overview as $row) {
            $totals[$row['status']->value]++;
        }

        return $totals;
    }
}
