@extends('layouts.app')
@section('title', 'Reportes · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Reportes</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
</div>

{{-- Income / expense headline --}}
<div class="row g-3 mb-4">
    <div class="col-sm-4" data-reveal>
        <div class="stat-card">
            <span class="stat-card__label"><i class="fa-solid fa-arrow-trend-up" style="color:var(--ok)"></i> Ingresos</span>
            <span class="stat-card__value">${{ number_format($ie['ingresos'], 2) }}</span>
        </div>
    </div>
    <div class="col-sm-4" data-reveal data-reveal-delay="40">
        <div class="stat-card">
            <span class="stat-card__label"><i class="fa-solid fa-arrow-trend-down" style="color:var(--warn)"></i> Gastos</span>
            <span class="stat-card__value">${{ number_format($ie['gastos'], 2) }}</span>
        </div>
    </div>
    <div class="col-sm-4" data-reveal data-reveal-delay="80">
        <div class="stat-card">
            <span class="stat-card__label"><i class="fa-solid fa-scale-balanced" style="color:var(--brand-500)"></i> Balance</span>
            <span class="stat-card__value" style="color:{{ $ie['balance'] >= 0 ? 'var(--ok)' : 'var(--danger)' }};">
                ${{ number_format($ie['balance'], 2) }}
            </span>
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- Cashflow chart --}}
    <div class="col-lg-8" data-reveal>
        <div class="card-clean">
            <div class="card-clean__head"><strong>Flujo del periodo</strong>
                <span class="text-muted" style="font-size:13px;">Cargos y depósitos por día</span>
            </div>
            <div class="card-clean__body">
                <canvas id="cashflow-chart" height="110"></canvas>
            </div>
        </div>
    </div>

    {{-- Report shortcuts --}}
    <div class="col-lg-4" data-reveal data-reveal-delay="60">
        <div class="card-clean">
            <div class="card-clean__head"><strong>Documentos</strong></div>
            <div class="card-clean__body" style="display:flex; flex-direction:column; gap:.625rem;">
                <a href="{{ route('reports.polizas') }}" class="report-link">
                    <span><i class="fa-solid fa-book"></i> Pólizas</span>
                    <i class="fa-solid fa-chevron-right text-muted"></i>
                </a>
                <a href="{{ route('reports.income_expense') }}" class="report-link">
                    <span><i class="fa-solid fa-chart-pie"></i> Ingresos y gastos</span>
                    <i class="fa-solid fa-chevron-right text-muted"></i>
                </a>
                <a href="{{ route('reconciliation.index') }}" class="report-link">
                    <span><i class="fa-solid fa-code-compare"></i> Resumen de conciliación</span>
                    <i class="fa-solid fa-chevron-right text-muted"></i>
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Reconciliation summary strip --}}
<div class="card-clean mt-3" data-reveal>
    <div class="card-clean__head"><strong>Resumen de conciliación</strong></div>
    <div class="card-clean__body">
        <div class="row g-3 text-center">
            <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:12px;">Facturas</div>
                <div class="data" style="font-size:1.25rem; font-weight:650;">{{ $summary['facturas_total'] }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:12px;">Movimientos</div>
                <div class="data" style="font-size:1.25rem; font-weight:650;">{{ $summary['movimientos_total'] }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:12px;">Enlaces confirmados</div>
                <div class="data" style="font-size:1.25rem; font-weight:650; color:var(--ok);">{{ $summary['enlaces_confirmados'] }}</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:12px;">Por revisar</div>
                <div class="data" style="font-size:1.25rem; font-weight:650; color:var(--warn);">{{ $summary['sin_factura'] + $summary['sin_movimiento'] }}</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
(function () {
    const ctx = document.getElementById('cashflow-chart');
    if (!ctx) return;

    const labels = @json($cashflow['labels']);
    const cargos = @json($cashflow['cargos']);
    const depositos = @json($cashflow['depositos']);

    // Read theme colors from CSS variables so the chart matches light/dark.
    const css = getComputedStyle(document.documentElement);
    const ok = css.getPropertyValue('--ok').trim() || '#067647';
    const warn = css.getPropertyValue('--warn').trim() || '#b54708';
    const grid = css.getPropertyValue('--border').trim() || '#e4e7ec';
    const text = css.getPropertyValue('--text-muted').trim() || '#818897';

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels.map(d => d.slice(5)), // MM-DD
            datasets: [
                { label: 'Depósitos', data: depositos, backgroundColor: ok, borderRadius: 4 },
                { label: 'Cargos', data: cargos, backgroundColor: warn, borderRadius: 4 },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { labels: { color: text, boxWidth: 12 } } },
            scales: {
                x: { grid: { display: false }, ticks: { color: text, font: { size: 10 } } },
                y: { grid: { color: grid }, ticks: { color: text, callback: v => '$' + v.toLocaleString() } },
            },
        },
    });
})();
</script>
@endpush
