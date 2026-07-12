@extends('layouts.app')
@section('title', 'Pólizas · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Pólizas</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.polizas.export') }}" class="btn btn-soft btn-icon"><i class="fa-solid fa-file-excel"></i> Excel</a>
        <a href="{{ route('reports.index') }}" class="btn btn-soft btn-icon"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</div>

@if($polizas->isEmpty())
    <div class="card-clean" data-reveal>
        <div class="empty-state">
            <i class="fa-solid fa-book"></i>
            <h3>Sin pólizas</h3>
            <p>Confirma enlaces en la conciliación para generar pólizas. Cada enlace confirmado produce una póliza contable.</p>
            <a href="{{ route('reconciliation.index') }}" class="btn btn-brand btn-icon mt-2"><i class="fa-solid fa-code-compare"></i> Ir a conciliación</a>
        </div>
    </div>
@else
    @foreach($polizas as $poliza)
        <div class="card-clean mb-3" data-reveal>
            <div class="card-clean__head">
                <div>
                    <strong>{{ $poliza['concepto'] }}</strong>
                    <span class="data text-muted" style="font-size:12px; display:block;">{{ $poliza['fecha'] }} · UUID {{ \Illuminate\Support\Str::limit($poliza['uuid'], 18) }}</span>
                </div>
                @if($poliza['cuadra'])
                    <span class="badge-status s-success"><i class="fa-solid fa-check"></i> Cuadra</span>
                @else
                    <span class="badge-status s-danger"><i class="fa-solid fa-triangle-exclamation"></i> No cuadra</span>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table-clean">
                    <thead><tr><th>Cuenta</th><th>Concepto</th><th class="text-end">Cargo</th><th class="text-end">Abono</th></tr></thead>
                    <tbody>
                        @foreach($poliza['lines'] as $line)
                            <tr>
                                <td>
                                    <span class="data" style="font-size:13px; font-weight:550;">{{ $line['numero_cuenta'] }}</span>
                                    <span class="text-muted d-block" style="font-size:11.5px;">{{ $line['nombre_cuenta'] }}</span>
                                </td>
                                <td style="font-size:13px;">{{ $line['concepto'] }}</td>
                                <td class="text-end data" style="font-size:13px;">{{ $line['cargo'] > 0 ? '$' . number_format($line['cargo'], 2) : '' }}</td>
                                <td class="text-end data" style="font-size:13px;">{{ $line['abono'] > 0 ? '$' . number_format($line['abono'], 2) : '' }}</td>
                            </tr>
                        @endforeach
                        <tr style="border-top:2px solid var(--border);">
                            <td colspan="2" class="text-end" style="font-weight:600;">Totales</td>
                            <td class="text-end data" style="font-weight:650;">${{ number_format($poliza['total_cargo'], 2) }}</td>
                            <td class="text-end data" style="font-weight:650;">${{ number_format($poliza['total_abono'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endif
@endsection
