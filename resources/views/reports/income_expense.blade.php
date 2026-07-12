@extends('layouts.app')
@section('title', 'Ingresos y gastos · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Ingresos y gastos</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.income_expense.export') }}" class="btn btn-soft btn-icon"><i class="fa-solid fa-file-excel"></i> Excel</a>
        <a href="{{ route('reports.index') }}" class="btn btn-soft btn-icon"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6" data-reveal><div class="stat-card"><span class="stat-card__label">Ingresos</span><span class="stat-card__value" style="font-size:1.4rem; color:var(--ok);">${{ number_format($ie['ingresos'], 2) }}</span></div></div>
    <div class="col-md-3 col-6" data-reveal data-reveal-delay="40"><div class="stat-card"><span class="stat-card__label">Gastos</span><span class="stat-card__value" style="font-size:1.4rem; color:var(--warn);">${{ number_format($ie['gastos'], 2) }}</span></div></div>
    <div class="col-md-3 col-6" data-reveal data-reveal-delay="80"><div class="stat-card"><span class="stat-card__label">IVA trasladado</span><span class="stat-card__value" style="font-size:1.4rem;">${{ number_format($ie['iva_trasladado'], 2) }}</span></div></div>
    <div class="col-md-3 col-6" data-reveal data-reveal-delay="120"><div class="stat-card"><span class="stat-card__label">IVA acreditable</span><span class="stat-card__value" style="font-size:1.4rem;">${{ number_format($ie['iva_acreditable'], 2) }}</span></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-6" data-reveal>
        <div class="card-clean">
            <div class="card-clean__head"><strong>Por cliente</strong><span class="text-muted" style="font-size:13px;">Ingresos</span></div>
            @if(empty($ie['por_cliente']))
                <div class="empty-state" style="padding:2rem;"><i class="fa-solid fa-inbox"></i><p>Sin facturas emitidas.</p></div>
            @else
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead><tr><th>Cliente</th><th class="text-end">Facturas</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @foreach($ie['por_cliente'] as $c)
                                <tr>
                                    <td><span style="font-size:13px; font-weight:500;">{{ $c['nombre'] }}</span><span class="data text-muted d-block" style="font-size:11px;">{{ $c['rfc'] }}</span></td>
                                    <td class="text-end data">{{ $c['count'] }}</td>
                                    <td class="text-end data" style="font-weight:550;">${{ number_format($c['total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    <div class="col-lg-6" data-reveal data-reveal-delay="60">
        <div class="card-clean">
            <div class="card-clean__head"><strong>Por proveedor</strong><span class="text-muted" style="font-size:13px;">Gastos</span></div>
            @if(empty($ie['por_proveedor']))
                <div class="empty-state" style="padding:2rem;"><i class="fa-solid fa-inbox"></i><p>Sin facturas recibidas.</p></div>
            @else
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead><tr><th>Proveedor</th><th class="text-end">Facturas</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @foreach($ie['por_proveedor'] as $c)
                                <tr>
                                    <td><span style="font-size:13px; font-weight:500;">{{ $c['nombre'] }}</span><span class="data text-muted d-block" style="font-size:11px;">{{ $c['rfc'] }}</span></td>
                                    <td class="text-end data">{{ $c['count'] }}</td>
                                    <td class="text-end data" style="font-weight:550;">${{ number_format($c['total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
