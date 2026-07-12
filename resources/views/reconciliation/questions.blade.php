@extends('layouts.app')
@section('title', 'Preguntas al cliente · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Preguntas al cliente</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-soft btn-icon" id="copy-btn"><i class="fa-solid fa-copy"></i> Copiar</button>
        <a href="{{ route('reconciliation.index') }}" class="btn btn-soft btn-icon"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</div>

@if($sinFactura->isEmpty() && $sinMovimiento->isEmpty())
    <div class="card-clean" data-reveal>
        <div class="empty-state">
            <i class="fa-solid fa-circle-check"></i>
            <h3>Nada pendiente</h3>
            <p>No hay movimientos ni facturas sin conciliar. No hay preguntas que enviar.</p>
        </div>
    </div>
@else
    <div class="card-clean" data-reveal>
        <div class="card-clean__body" id="questions-body">
            <p style="font-size:14px;">Hola, para cerrar la contabilidad de <strong>{{ $period->label }}</strong> necesito aclarar lo siguiente:</p>

            @if($sinFactura->isNotEmpty())
                <h3 style="font-size:15px; font-weight:600; margin:1.25rem 0 .5rem;">
                    Movimientos en el banco sin factura ({{ $sinFactura->count() }})
                </h3>
                <p class="text-muted" style="font-size:13px; margin-bottom:.5rem;">¿Me puedes enviar la factura (CFDI) de estos cargos/depósitos?</p>
                <ol style="font-size:13.5px; padding-left:1.25rem;">
                    @foreach($sinFactura as $mov)
                        <li style="margin-bottom:.35rem;">
                            <span class="data">{{ $mov->fecha?->format('d/m/Y') }}</span> —
                            {{ $mov->descripcion }} —
                            <strong class="data">${{ number_format($mov->cargo > 0 ? $mov->cargo : $mov->deposito, 2) }}</strong>
                            ({{ $mov->cargo > 0 ? 'cargo' : 'depósito' }})
                        </li>
                    @endforeach
                </ol>
            @endif

            @if($sinMovimiento->isNotEmpty())
                <h3 style="font-size:15px; font-weight:600; margin:1.25rem 0 .5rem;">
                    Facturas sin pago identificado ({{ $sinMovimiento->count() }})
                </h3>
                <p class="text-muted" style="font-size:13px; margin-bottom:.5rem;">No encontré el pago de estas facturas en el estado de cuenta. ¿Se pagaron por otra cuenta, en otro mes, o siguen pendientes?</p>
                <ol style="font-size:13.5px; padding-left:1.25rem;">
                    @foreach($sinMovimiento as $inv)
                        <li style="margin-bottom:.35rem;">
                            <span class="data">{{ $inv->fecha_emision?->format('d/m/Y') }}</span> —
                            {{ $inv->tipo === 'emitida' ? ($inv->receptor_nombre ?: $inv->receptor_rfc) : ($inv->emisor_nombre ?: $inv->emisor_rfc) }} —
                            folio <span class="data">{{ $inv->serie }}{{ $inv->folio }}</span> —
                            <strong class="data">${{ number_format($inv->total, 2) }}</strong>
                        </li>
                    @endforeach
                </ol>
            @endif

            <p style="font-size:14px; margin-top:1.25rem;">Gracias.</p>
        </div>
    </div>
@endif

@endsection

@push('scripts')
<script>
document.getElementById('copy-btn')?.addEventListener('click', () => {
    const text = document.getElementById('questions-body')?.innerText || '';
    navigator.clipboard.writeText(text).then(
        () => App.toast.success('Copiado al portapapeles.'),
        () => App.toast.error('No se pudo copiar.')
    );
});
</script>
@endpush
