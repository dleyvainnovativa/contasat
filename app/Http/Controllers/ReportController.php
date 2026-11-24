<?php

namespace App\Http\Controllers;

use App\Services\PolizaBuilder;
use App\Services\ReportExporter;
use App\Services\ReportService;
use App\Services\TrialBalanceService;
use App\Services\WorkContext;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
        private readonly ReportService $reports,
        private readonly PolizaBuilder $polizas,
        private readonly ReportExporter $exporter,
        private readonly TrialBalanceService $trial,
    ) {}

    private function requirePeriod(): ?RedirectResponse
    {
        if (! $this->context->hasPeriod()) {
            return redirect()->route('dashboard')
                ->with('toast', ['type' => 'warning', 'message' => 'Selecciona un cliente y periodo primero.']);
        }

        return null;
    }

    /** Reports hub: summary cards + cashflow chart. */
    public function index(): View|RedirectResponse
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        $period = $this->context->period();

        return view('reports.index', [
            'period'    => $period,
            'summary'   => $this->reports->reconciliationSummary($period),
            'ie'        => $this->reports->incomeExpense($period),
            'cashflow'  => $this->reports->dailyCashflow($period),
        ]);
    }

    public function polizas(): View|RedirectResponse
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        $period = $this->context->period();

        return view('reports.polizas', [
            'period'  => $period,
            'polizas' => $this->polizas->build($period),
        ]);
    }

    public function incomeExpense(): View|RedirectResponse
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        $period = $this->context->period();

        return view('reports.income_expense', [
            'period' => $period,
            'ie'     => $this->reports->incomeExpense($period),
        ]);
    }

    /** Balanza de comprobación for the active period. */
    public function balanza(Request $request): View|RedirectResponse
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        $period = $this->context->period();
        $data   = $this->trial->build($period);

        $mostrar = $request->boolean('mostrar');   // show accounts without movements
        $q       = $request->string('q')->toString();

        $rows = collect($data['rows']);
        if (! $mostrar) {
            $rows = $rows->where('has_mov', true);
        }
        if ($q !== '') {
            $term = mb_strtolower($q);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['nombre']), $term)
                || str_contains(mb_strtolower($r['numero_cuenta']), $term));
        }
        $rows = $rows->values();

        $perPage = $this->perPage($request);
        $page    = (int) $request->integer('page', 1);
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('reports.balanza', [
            'period'  => $period,
            'cards'   => $data['cards'],
            'rows'    => $paginator,
            'q'       => $q,
            'mostrar' => $mostrar,
            'perPage' => $perPage,
        ]);
    }

    public function exportBalanza(): BinaryFileResponse|RedirectResponse
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        $period = $this->context->period();
        $data   = $this->trial->build($period);
        $path   = $this->exporter->balanzaXlsx($period, $data['rows'], $data['cards']);

        return response()->download($path, "balanza_{$period->label}.xlsx")->deleteFileAfterSend();
    }

    /** Rows per page: one of 25/100/200/500, default 100. */
    private function perPage(Request $request): int
    {
        $allowed = [25, 100, 200, 500];
        $requested = (int) $request->integer('per_page', 100);

        return in_array($requested, $allowed, true) ? $requested : 100;
    }

    public function exportPolizas(): BinaryFileResponse|RedirectResponse
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        $period = $this->context->period();
        $path = $this->exporter->polizasXlsx($period, $this->polizas->build($period));

        return response()->download($path, "polizas_{$period->label}.xlsx")->deleteFileAfterSend();
    }

    public function exportIncomeExpense(): BinaryFileResponse|RedirectResponse
    {
        if ($r = $this->requirePeriod()) {
            return $r;
        }

        $period = $this->context->period();
        $path = $this->exporter->incomeExpenseXlsx($period, $this->reports->incomeExpense($period));

        return response()->download($path, "ingresos_gastos_{$period->label}.xlsx")->deleteFileAfterSend();
    }
}
