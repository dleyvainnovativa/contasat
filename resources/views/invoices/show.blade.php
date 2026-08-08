@extends('layouts.app')
@section('title', 'Factura ' . ($invoice->serie . $invoice->folio) . ' · ContaSAT')

@section('content')
@php
$isEmitida = $invoice->tipo === 'emitida';
$counterName = $isEmitida ? ($invoice->receptor_nombre ?: $invoice->receptor_rfc) : ($invoice->emisor_nombre ?: $invoice->emisor_rfc);
$counterRfc = $isEmitida ? $invoice->receptor_rfc : $invoice->emisor_rfc;
$tipoLabels = ['I' => 'Ingreso', 'E' => 'Egreso', 'N' => 'Nómina', 'P' => 'Pago', 'T' => 'Traslado'];
$tipoLabel = $tipoLabels[$invoice->tipo_comprobante] ?? $invoice->tipo_comprobante;
$isPago = $invoice->tipo_comprobante === 'P';
@endphp

<div class="page-head" data-reveal>
    <div>
        <div class="d-flex align-items-center gap-2">
            <a href="{{ url()->previous() }}" class="btn btn-soft" style="padding:.3rem .55rem;"><i class="fa-solid fa-arrow-left"></i></a>
            <h1 style="margin:0;">Factura {{ $invoice->serie }}{{ $invoice->folio }}</h1>
        </div>
        <div class="subtitle">{{ $invoice->client->display_name }} · {{ $tipoLabel }} · {{ $isEmitida ? 'Emitida' : 'Recibida' }}</div>
    </div>
    <a href="{{ route('invoices.xml', $invoice) }}" class="btn btn-soft btn-icon">
        <i class="fa-solid fa-code"></i> XML
    </a>
</div>

<div class="row g-3">
    {{-- Left: parties + fiscal data --}}
    <div class="col-lg-7">
        <div class="card-clean" data-reveal>
            <div class="card-clean__head"><strong>Comprobante</strong>
                @php
                $cls = match($invoice->clasificacion ?? 'sin_clasificar') {
                'clasificada' => ['s-success', 'Clasificada'],
                'sugerida' => ['s-info', 'Sugerida'],
                default => ['s-secondary', 'Sin clasificar'],
                };
                @endphp
                <span class="badge-status {{ $cls[0] }}" style="font-size:11px;">{{ $cls[1] }}</span>
            </div>
            <div class="card-clean__body">
                <div class="detail-grid">
                    <div><span class="detail-label">UUID</span><span class="data" style="font-size:11.5px;">{{ $invoice->uuid }}</span></div>
                    <div><span class="detail-label">Fecha emisión</span><span class="data">{{ $invoice->fecha_emision?->format('d/m/Y H:i') }}</span></div>
                    <div><span class="detail-label">Fecha timbrado</span><span class="data">{{ $invoice->fecha_timbrado?->format('d/m/Y H:i') ?? '—' }}</span></div>
                    <div><span class="detail-label">Serie · Folio</span><span class="data">{{ $invoice->serie ?: '—' }} · {{ $invoice->folio ?: '—' }}</span></div>
                    <div><span class="detail-label">Moneda</span><span class="data">{{ $invoice->moneda ?: 'MXN' }} @if($invoice->tipo_cambio && $invoice->tipo_cambio != 1)· TC {{ $invoice->tipo_cambio }}@endif</span></div>
                    <div><span class="detail-label">Tipo comprobante</span><span>{{ $tipoLabel }}</span></div>
                </div>

                <hr style="border-color:var(--border); margin:1rem 0;">

                <div class="detail-grid">
                    <div>
                        <span class="detail-label">{{ $isEmitida ? 'Receptor' : 'Emisor' }}</span>
                        <span style="font-weight:550;">{{ $counterName }}</span>
                        <span class="data text-muted" style="font-size:12px;">{{ $counterRfc }}</span>
                    </div>
                    <div>
                        <span class="detail-label">{{ $isEmitida ? 'Emisor (cliente)' : 'Receptor (cliente)' }}</span>
                        <span style="font-weight:550;">{{ $isEmitida ? ($invoice->emisor_nombre ?: $invoice->emisor_rfc) : ($invoice->receptor_nombre ?: $invoice->receptor_rfc) }}</span>
                        <span class="data text-muted" style="font-size:12px;">{{ $isEmitida ? $invoice->emisor_rfc : $invoice->receptor_rfc }}</span>
                    </div>
                </div>

                @unless($isPago)
                <hr style="border-color:var(--border); margin:1rem 0;">
                <div class="detail-grid">
                    <div><span class="detail-label">Forma de pago</span><span>{{ $invoice->forma_pago ?: '—' }}</span></div>
                    <div><span class="detail-label">Método de pago</span><span>{{ $invoice->metodo_pago ?: '—' }}</span></div>
                    <div><span class="detail-label">Uso CFDI</span><span>{{ $invoice->uso_cfdi ?: '—' }}</span></div>
                </div>
                @endunless
            </div>
        </div>

        {{-- Concepto lines (not for pago) --}}
        @unless($isPago)
        <div class="card-clean mt-3" data-reveal>
            <div class="card-clean__head"><strong>Conceptos</strong></div>
            <div class="table-responsive">
                <table class="table-clean">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th class="text-end">Cantidad</th>
                            <th class="text-end">V. unitario</th>
                            <th class="text-end">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->lines as $line)
                        <tr>
                            <td style="font-size:12.5px; max-width:280px;">
                                {{ $line->descripcion }}
                                @if($line->clave_prod_serv)
                                <span class="data text-muted" style="font-size:10.5px; display:block;">{{ $line->clave_prod_serv }}</span>
                                @endif
                            </td>
                            <td class="text-end data">{{ rtrim(rtrim(number_format($line->cantidad, 2), '0'), '.') }}</td>
                            <td class="text-end data">{{ number_format($line->valor_unitario, 2) }}</td>
                            <td class="text-end data">{{ number_format($line->importe, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endunless

        {{-- Pago complement detail (Block C) --}}
        @if($isPago && $paymentLinks->isNotEmpty())
        <div class="card-clean mt-3" data-reveal>
            <div class="card-clean__head"><strong>Documentos que liquida</strong></div>
            <div class="table-responsive">
                <table class="table-clean">
                    <thead>
                        <tr>
                            <th>UUID pagado</th>
                            <th>Fecha</th>
                            <th class="text-center">Parc.</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-center">Vinc.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($paymentLinks as $link)
                        <tr>
                            <td class="data text-muted" style="font-size:11px;" title="{{ $link->iddocumento }}">{{ \Illuminate\Support\Str::limit($link->iddocumento, 18, '…') }}</td>
                            <td class="data">{{ $link->fecha_pago?->format('d/m/Y') }}</td>
                            <td class="text-center data">{{ $link->num_parcialidad ?? '—' }}</td>
                            <td class="text-end data" style="font-weight:600;">{{ number_format($link->imp_pagado, 2) }}</td>
                            <td class="text-center">
                                @if($link->isResolved())
                                <span class="badge-status s-success" style="font-size:10px;"><i class="fa-solid fa-link"></i></span>
                                @else
                                <span class="badge-status s-secondary" style="font-size:10px;"><i class="fa-solid fa-link-slash"></i></span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    </div>

    {{-- Right: amounts + accounting --}}
    <div class="col-lg-5">
        <div class="card-clean" data-reveal>
            <div class="card-clean__head"><strong>Importes</strong></div>
            <div class="card-clean__body">
                <div class="amount-row"><span>Subtotal</span><span class="data">${{ number_format($invoice->subtotal, 2) }}</span></div>
                @if($invoice->descuento > 0)
                <div class="amount-row"><span>Descuento</span><span class="data">−${{ number_format($invoice->descuento, 2) }}</span></div>
                @endif
                <div class="amount-row"><span>IVA trasladado</span><span class="data">${{ number_format($invoice->iva_trasladado ?? 0, 2) }}</span></div>
                @if(($invoice->iva_retenido ?? 0) > 0)
                <div class="amount-row"><span>Ret. IVA</span><span class="data">−${{ number_format($invoice->iva_retenido, 2) }}</span></div>
                @endif
                @if(($invoice->isr_retenido ?? 0) > 0)
                <div class="amount-row"><span>Ret. ISR</span><span class="data">−${{ number_format($invoice->isr_retenido, 2) }}</span></div>
                @endif
                <div class="amount-row amount-row--total"><span>Total</span><span class="data">${{ number_format($invoice->total, 2) }}</span></div>
            </div>
        </div>

        @unless($isPago)
        <div class="card-clean mt-3" data-reveal>
            <div class="card-clean__head"><strong>Cuentas contables</strong></div>
            <div class="card-clean__body">
                <div class="mb-3">
                    <span class="detail-label">Cuenta contable (contraparte)</span>
                    @if($invoice->cuentaContable)
                    <span class="data">{{ $invoice->cuentaContable->numero_cuenta }} — {{ $invoice->cuentaContable->nombre }}</span>
                    @else
                    <span class="text-muted">Sin asignar</span>
                    @endif
                </div>
                <div>
                    <span class="detail-label">Cuenta de abono</span>
                    @if($invoice->cuentaAbono)
                    <span class="data">{{ $invoice->cuentaAbono->numero_cuenta }} — {{ $invoice->cuentaAbono->nombre }}</span>
                    @else
                    <span class="text-muted">Sin asignar</span>
                    @endif
                </div>
            </div>
        </div>
        @endunless

        {{-- Which payments cleared this invoice (reverse Block C link) --}}
        @if($paidByLinks->isNotEmpty())
        <div class="card-clean mt-3" data-reveal>
            <div class="card-clean__head"><strong>Pagos recibidos</strong></div>
            <div class="card-clean__body" style="padding-top:.5rem;">
                @foreach($paidByLinks as $link)
                <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid var(--border); font-size:12.5px;">
                    <span class="data text-muted">{{ $link->fecha_pago?->format('d/m/Y') }}</span>
                    <span class="data" style="font-weight:600;">${{ number_format($link->imp_pagado, 2) }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@if($invoice->tipo === 'emitida' && $invoice->tipo_comprobante === 'I')
@include('invoices._provision_cobro')
@endif
@endsection