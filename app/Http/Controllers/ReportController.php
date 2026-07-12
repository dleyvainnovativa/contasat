<?php

namespace App\Http\Controllers;

use App\Services\PolizaBuilder;
use App\Services\ReportExporter;
use App\Services\ReportService;
use App\Services\WorkContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly WorkContext $context,
        private readonly ReportService $reports,
        private readonly PolizaBuilder $polizas,
        private readonly ReportExporter $exporter,
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
