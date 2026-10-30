@extends('layouts.app')
@section('title', $label . ' · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>{{ $label }}</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
</div>

{{-- Submenu tabs --}}
<div class="fac-tabs" data-reveal>
    @php
    $tabs = [
    'ingreso' => ['Ingreso', 'fa-arrow-down'],
    'gasto' => ['Gastos', 'fa-arrow-up'],
    'nomina' => ['Nómina', 'fa-users'],
    'pago_emitido' => ['Pago emitidos', 'fa-money-bill-transfer'],
    'pago_recibido' => ['Pago recibidos', 'fa-money-bill-wave'],
    ];
    @endphp
    @foreach($tabs as $key => [$tabLabel, $icon])
    <a href="{{ route('invoices.view', $key) }}"
        class="fac-tab {{ $view === $key ? 'active' : '' }}">
        <i class="fa-solid {{ $icon }}"></i>
        <span>{{ $tabLabel }}</span>
        @if(($navItems[$key] ?? 0) > 0)
        <span class="fac-tab__count">{{ $navItems[$key] }}</span>
        @endif
    </a>
    @endforeach
</div>

{{-- Totals strip --}}
<div class="row g-3 mb-4" data-reveal>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card__value">{{ number_format($totals['count']) }}</div>
            <div class="stat-card__label">Facturas</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card__value">${{ number_format($totals['subtotal'], 2) }}</div>
            <div class="stat-card__label">Subtotal</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card__value">${{ number_format($totals['iva'], 2) }}</div>
            <div class="stat-card__label">IVA</div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-card__value">${{ number_format($totals['total'], 2) }}</div>
            <div class="stat-card__label">Total</div>
        </div>
    </div>
</div>

<div class="card-clean" data-reveal>
    <div class="card-clean__head">
        <form method="get" class="d-flex gap-2" style="flex:1;">
            <input type="search" name="q" value="{{ $q }}" class="form-control"
                placeholder="Buscar folio, UUID, RFC o nombre…" style="max-width:360px;">
        </form>
    </div>

    {{-- Every invoice module now renders a purpose-built wide table. --}}
    @switch($view)
        @case('ingreso')
            @include('invoices._ingreso_wide')
            @break
        @case('gasto')
            @include('invoices._gasto_wide')
            @break
        @case('nomina')
            @include('invoices._nomina_wide')
            @break
        @default
            @include('invoices._pago_wide')
    @endswitch
</div>

{{-- Per-document breakdown of what each pago settles. --}}
@if($isPago)
@include('invoices._pago_detail')
@endif

@endsection
