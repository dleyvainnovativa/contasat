@extends('layouts.app')
@section('title', 'Factura · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Factura {{ $invoice->serie }}{{ $invoice->folio }}</h1>
        <div class="subtitle data">{{ $invoice->uuid }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('invoices.xml', $invoice) }}" class="btn btn-soft btn-icon"><i class="fa-solid fa-download"></i> XML</a>
        <a href="{{ route('invoices.index') }}" class="btn btn-soft btn-icon"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</div>

<div class="row g-3">
    {{-- Header details --}}
    <div class="col-lg-5" data-reveal>
        <div class="card-clean">
            <div class="card-clean__head">
                <strong>Comprobante</strong>
                @if($invoice->tipo === 'emitida')
                    <span class="badge-status s-success"><i class="fa-solid fa-arrow-up"></i> Emitida</span>
                @else
                    <span class="badge-status s-warning"><i class="fa-solid fa-arrow-down"></i> Recibida</span>
                @endif
            </div>
            <div class="card-clean__body">
                <dl class="mb-0" style="display:grid; grid-template-columns:auto 1fr; gap:.55rem 1rem; font-size:13.5px;">
                    <dt class="text-muted">Emisor</dt>
                    <dd class="mb-0">{{ $invoice->emisor_nombre ?: '—' }}<br><span class="data text-muted" style="font-size:12px;">{{ $invoice->emisor_rfc }}</span></dd>

                    <dt class="text-muted">Receptor</dt>
                    <dd class="mb-0">{{ $invoice->receptor_nombre ?: '—' }}<br><span class="data text-muted" style="font-size:12px;">{{ $invoice->receptor_rfc }}</span></dd>

                    <dt class="text-muted">Fecha</dt><dd class="mb-0 data">{{ $invoice->fecha_emision?->format('d/m/Y H:i') }}</dd>
                    <dt class="text-muted">Tipo</dt><dd class="mb-0">{{ $invoice->tipo_comprobante }} · {{ $invoice->metodo_pago }}</dd>
                    <dt class="text-muted">Forma pago</dt><dd class="mb-0">{{ $invoice->forma_pago ?: '—' }}</dd>
                    <dt class="text-muted">Uso CFDI</dt><dd class="mb-0">{{ $invoice->uso_cfdi ?: '—' }}</dd>
                    <dt class="text-muted">Moneda</dt><dd class="mb-0 data">{{ $invoice->moneda }}</dd>
                </dl>
            </div>
        </div>

        {{-- Money summary --}}
        <div class="card-clean mt-3">
            <div class="card-clean__body">
                <div class="d-flex justify-content-between py-1" style="font-size:14px;">
                    <span class="text-muted">Subtotal</span><span class="data">${{ number_format($invoice->subtotal, 2) }}</span>
                </div>
                @if($invoice->descuento > 0)
                    <div class="d-flex justify-content-between py-1" style="font-size:14px;">
                        <span class="text-muted">Descuento</span><span class="data">−${{ number_format($invoice->descuento, 2) }}</span>
                    </div>
                @endif
                <div class="d-flex justify-content-between py-2 mt-1" style="border-top:1px solid var(--border); font-weight:650;">
                    <span>Total</span><span class="data">${{ number_format($invoice->total, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Concepto lines --}}
    <div class="col-lg-7" data-reveal data-reveal-delay="60">
        <div class="card-clean">
            <div class="card-clean__head">
                <strong>Conceptos</strong>
                <span class="text-muted" style="font-size:13px;">{{ $invoice->lines->count() }} líneas</span>
            </div>
            <div class="table-responsive">
                <table class="table-clean">
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th class="text-end">Cant.</th>
                            <th class="text-end">P. unitario</th>
                            <th class="text-end">Importe</th>
                            <th class="text-end">IVA</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice->lines as $line)
                            <tr>
                                <td style="font-size:13px;">
                                    {{ $line->descripcion }}
                                    @if($line->clave_prod_serv)
                                        <span class="data text-muted d-block" style="font-size:11px;">{{ $line->clave_prod_serv }}</span>
                                    @endif
                                </td>
                                <td class="text-end data" style="font-size:13px;">{{ rtrim(rtrim(number_format($line->cantidad, 2), '0'), '.') }}</td>
                                <td class="text-end data" style="font-size:13px;">${{ number_format($line->valor_unitario, 2) }}</td>
                                <td class="text-end data" style="font-size:13px;">${{ number_format($line->importe, 2) }}</td>
                                <td class="text-end data text-muted" style="font-size:13px;">${{ number_format($line->iva_trasladado, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
