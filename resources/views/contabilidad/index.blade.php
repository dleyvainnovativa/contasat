@extends('layouts.app')
@section('title', 'Contabilidad electrónica · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Contabilidad electrónica</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
    <a href="{{ route('contabilidad.zip') }}" class="btn btn-brand btn-icon">
        <i class="fa-solid fa-file-zipper"></i> Descargar ZIP
    </a>
</div>

<div class="card-clean mb-4" data-reveal style="border-left:3px solid var(--brand-500);">
    <div class="card-clean__body">
        <div class="d-flex align-items-start gap-3">
            <i class="fa-solid fa-circle-info" style="font-size:1.25rem; color:var(--brand-500); margin-top:2px;"></i>
            <div style="font-size:13.5px;">
                <strong>Tres archivos para el SAT (Anexo 24, versión 1.3).</strong>
                Se generan a partir de las pólizas conciliadas del periodo. Cada uno se valida antes de marcarse como listo.
                Para presentarlos, envíalos comprimidos en ZIP por el Buzón Tributario del SAT.
            </div>
        </div>
    </div>
</div>

@php
    $labels = [
        'catalogo' => ['Catálogo de cuentas', 'fa-sitemap', 'Estructura de cuentas con código agrupador SAT'],
        'balanza'  => ['Balanza de comprobación', 'fa-scale-balanced', 'Saldos y movimientos del periodo por cuenta'],
        'polizas'  => ['Pólizas del periodo', 'fa-book', 'Asientos contables con referencia al UUID del CFDI'],
    ];
@endphp

<div class="row g-3">
    @foreach($docs as $type => $doc)
        @php [$title, $icon, $desc] = $labels[$type]; @endphp
        <div class="col-lg-4" data-reveal data-reveal-delay="{{ $loop->index * 60 }}">
            <div class="card-clean" style="height:100%;">
                <div class="card-clean__body" style="display:flex; flex-direction:column; height:100%;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span style="width:36px; height:36px; border-radius:8px; background:var(--brand-050); color:var(--brand-600); display:grid; place-items:center;">
                            <i class="fa-solid {{ $icon }}"></i>
                        </span>
                        <div>
                            <div style="font-weight:600; font-size:14px;">{{ $title }}</div>
                            <div class="data text-muted" style="font-size:11px;">{{ $doc['filename'] }}</div>
                        </div>
                    </div>

                    <p class="text-muted" style="font-size:12.5px; flex-grow:1;">{{ $desc }}</p>

                    {{-- Validation status --}}
                    @if($doc['valid'])
                        <div class="badge-status s-success mb-3" style="width:fit-content;">
                            <i class="fa-solid fa-circle-check"></i>
                            Válido ({{ $doc['method'] === 'xsd' ? 'XSD' : 'estructura' }})
                        </div>
                    @else
                        <div class="badge-status s-danger mb-2" style="width:fit-content;">
                            <i class="fa-solid fa-triangle-exclamation"></i> No válido
                        </div>
                        <details class="mb-3" style="font-size:12px;">
                            <summary style="cursor:pointer; color:var(--danger);">Ver errores ({{ count($doc['errors']) }})</summary>
                            <ul style="margin:.5rem 0 0; padding-left:1.1rem; color:var(--text-soft);">
                                @foreach(array_slice($doc['errors'], 0, 8) as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </details>
                    @endif

                    <div class="d-flex gap-2">
                        <a href="{{ route('contabilidad.download', $type) }}" class="btn btn-soft btn-icon" style="flex:1; justify-content:center; font-size:12.5px;">
                            <i class="fa-solid fa-download"></i> XML
                        </a>
                        <a href="{{ route('contabilidad.preview', $type) }}" target="_blank" class="btn btn-soft btn-icon" style="font-size:12.5px;">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card-clean mt-4" data-reveal>
    <div class="card-clean__body">
        <div class="form-hint" style="font-size:12px;">
            <i class="fa-solid fa-shield-halved"></i>
            <strong>Validación XSD:</strong> para la validación oficial contra los esquemas del SAT, coloca los archivos
            <span class="data">CatalogoCuentas_1_3.xsd</span>, <span class="data">BalanzaComprobacion_1_3.xsd</span> y
            <span class="data">PolizasPeriodo_1_3.xsd</span> en <span class="data">storage/app/xsd/</span>.
            Sin ellos, se aplica validación estructural (raíz, atributos requeridos y nodos de detalle).
            El sellado con la e.firma se realiza en el Buzón Tributario al momento del envío.
        </div>
    </div>
</div>
@endsection
