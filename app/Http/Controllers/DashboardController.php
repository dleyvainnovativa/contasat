<?php

namespace App\Http\Controllers;

use App\Enums\PeriodStatus;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function index(Request $request): View
    {
        // Default to the current month; allow ?year= & ?month= overrides.
        $now   = now();
        $year  = (int) $request->integer('year', $now->year);
        $month = (int) $request->integer('month', $now->month);

        // Clamp month to a valid range.
        $month = max(1, min(12, $month));

        $overview = $this->dashboard->overview($year, $month);
        $totals   = $this->dashboard->statusTotals($year, $month);

        return view('dashboard.index', [
            'overview'    => $overview,
            'totals'      => $totals,
            'year'        => $year,
            'month'       => $month,
            'statuses'    => PeriodStatus::cases(),
            'monthLabel'  => $this->monthLabel($month) . ' ' . $year,
        ]);
    }

    private function monthLabel(int $month): string
    {
        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                  7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

        return $meses[$month] ?? (string) $month;
    }
}
