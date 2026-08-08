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

    @if($invoices->isEmpty())
    <div class="empty-state">
        <i class="fa-solid fa-file-invoice"></i>
        <h3>Sin facturas</h3>
        <p>No hay comprobantes de este tipo en el periodo.</p>
    </div>
    @else
    <div class="table-responsive">
        <table class="table-clean table-wide">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Folio</th>
                    <th>UUID</th>
                    <th>{{ $view === 'ingreso' || $view === 'pago_emitido' ? 'RFC Receptor' : 'RFC Emisor' }}</th>
                    <th>{{ $view === 'ingreso' || $view === 'pago_emitido' ? 'Nombre Receptor' : 'Nombre Emisor' }}</th>
                    <th>Cuenta contable</th>
                    @unless($isPago)
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">IVA</th>
                    <th class="text-end">Ret. IVA</th>
                    <th class="text-end">Ret. ISR</th>
                    @endunless
                    <th class="text-end">Total</th>
                    @unless($isPago || $isNomina)
                    <th>Forma pago</th>
                    <th>Método</th>
                    <th>Uso CFDI</th>
                    @endunless
                    <th>Clasificación</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoices as $inv)
                @php
                $isEmitida = in_array($view, ['ingreso', 'pago_emitido']);
                $rfc = $isEmitida ? $inv->receptor_rfc : $inv->emisor_rfc;
                $name = $isEmitida ? $inv->receptor_nombre : $inv->emisor_nombre;
                @endphp
                <tr>
                    <td class="data" style="white-space:nowrap;">{{ $inv->fecha_emision?->format('d/m/Y') }}</td>
                    <td class="data">{{ $inv->serie }}{{ $inv->folio }}</td>
                    <td class="data text-muted" style="font-size:11px;" title="{{ $inv->uuid }}">
                        {{ \Illuminate\Support\Str::limit($inv->uuid, 13, '…') }}
                    </td>
                    <td class="data" style="font-size:12px;">{{ $rfc }}</td>
                    <td style="font-size:12.5px; max-width:200px;" class="text-truncate" title="{{ $name }}">{{ $name ?: '—' }}</td>
                    <td class="data" style="font-size:12px;">
                        @if($inv->cuentaContable)
                        {{ $inv->cuentaContable->numero_cuenta }}
                        @else
                        <span class="text-muted">—</span>
                        @endif
                    </td>
                    @unless($isPago)
                    <td class="text-end data">{{ number_format($inv->subtotal, 2) }}</td>
                    <td class="text-end data">{{ number_format($inv->iva_trasladado, 2) }}</td>
                    <td class="text-end data">{{ $inv->iva_retenido > 0 ? number_format($inv->iva_retenido, 2) : '—' }}</td>
                    <td class="text-end data">{{ $inv->isr_retenido > 0 ? number_format($inv->isr_retenido, 2) : '—' }}</td>
                    @endunless
                    <td class="text-end data" style="font-weight:600;">{{ number_format($inv->total, 2) }}</td>
                    @unless($isPago || $isNomina)
                    <td class="data text-muted" style="font-size:11px;">{{ $inv->forma_pago ?: '—' }}</td>
                    <td class="data text-muted" style="font-size:11px;">{{ $inv->metodo_pago ?: '—' }}</td>
                    <td class="data text-muted" style="font-size:11px;">{{ $inv->uso_cfdi ?: '—' }}</td>
                    @endunless
                    <td>
                        @php
                        $cls = match($inv->clasificacion) {
                        'clasificada' => ['s-success', 'Clasificada'],
                        'sugerida' => ['s-info', 'Sugerida'],
                        default => ['s-secondary', 'Sin clasificar'],
                        };
                        @endphp
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge-status {{ $cls[0] }}" style="font-size:11px;">{{ $cls[1] }}</span>
                            @unless($isPago || $isNomina)
                            <button class="btn btn-soft" style="padding:.2rem .45rem; font-size:11px;"
                                data-classify="{{ $inv->id }}" title="Clasificar / confirmar">
                                <i class="fa-solid fa-pen"></i>
                            </button>
                            <a href="{{ route('invoices.show', $inv) }}" class="btn btn-soft"
                                style="padding:.2rem .45rem; font-size:11px;" title="Ver factura">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            @endunless
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<div class="mt-3">{{ $invoices->links() }}</div>

@if($isPago)
@include('invoices._pago_detail')
@endif

@unless($isPago || $isNomina)
@include('invoices._classify_modal')
@endunless

@endsection