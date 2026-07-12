@extends('layouts.app')
@section('title', 'Estado de cuenta · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>{{ $statement->banco ?: 'Estado de cuenta' }}</h1>
        <div class="subtitle">
            {{ $statement->client->display_name }}
            @if($statement->fecha_inicio) · {{ $statement->fecha_inicio->format('d/m/Y') }} – {{ $statement->fecha_fin?->format('d/m/Y') }}@endif
        </div>
    </div>
    <div class="d-flex gap-2">
        @if($statement->extraccion_status === 'revision' || $statement->extraccion_status === 'error')
            <button class="btn btn-soft btn-icon" id="reextract-btn">
                <i class="fa-solid fa-rotate"></i> Reprocesar
            </button>
        @endif
        <a href="{{ route('statements.index') }}" class="btn btn-soft btn-icon"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
</div>

{{-- Balance verdict — the hard gate, made visible --}}
<div class="card-clean mb-4" data-reveal style="border-left:3px solid var(--{{ $statement->balance_cuadra ? 'ok' : 'warn' }});">
    <div class="card-clean__body">
        <div class="d-flex align-items-start gap-3">
            <div style="font-size:1.5rem; color:var(--{{ $statement->balance_cuadra ? 'ok' : 'warn' }}); margin-top:2px;">
                <i class="fa-solid fa-{{ $statement->balance_cuadra ? 'circle-check' : 'triangle-exclamation' }}"></i>
            </div>
            <div class="flex-grow-1">
                <div style="font-weight:600; margin-bottom:.25rem;">
                    @if($statement->balance_cuadra)
                        El balance cuadra
                    @else
                        El balance no cuadra — requiere revisión
                    @endif
                </div>
                <p class="text-muted mb-3" style="font-size:13px;">
                    @if($statement->balance_cuadra)
                        Saldo inicial + depósitos − cargos coincide con el saldo final. Este estado está listo para conciliar.
                    @else
                        La suma de movimientos no coincide con el saldo final declarado. Revisa el PDF antes de conciliar: puede faltar un movimiento o la extracción tuvo un error.
                    @endif
                </p>

                {{-- Reconciliation math, shown transparently --}}
                <div class="row g-3" style="font-size:13.5px;">
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:12px;">Saldo inicial</div>
                        <div class="data">${{ number_format($statement->saldo_inicial ?? 0, 2) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:12px;">+ Depósitos</div>
                        <div class="data" style="color:var(--ok);">${{ number_format($sumDepositos, 2) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:12px;">− Cargos</div>
                        <div class="data" style="color:var(--warn);">${{ number_format($sumCargos, 2) }}</div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="text-muted" style="font-size:12px;">= Calculado / Declarado</div>
                        <div class="data">
                            ${{ number_format($computedFinal, 2) }}
                            <span class="text-muted">/ ${{ number_format($statement->saldo_final ?? 0, 2) }}</span>
                        </div>
                    </div>
                </div>

                @if(!$statement->balance_cuadra && $difference !== null)
                    <div class="mt-2" style="font-size:13px;">
                        <span class="text-muted">Diferencia:</span>
                        <span class="data" style="color:var(--danger); font-weight:550;">${{ number_format(abs($difference), 2) }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($statement->extraccion_status === 'error')
    <div class="card-clean mb-4" data-reveal style="border-left:3px solid var(--danger);">
        <div class="card-clean__body">
            <strong style="color:var(--danger);">Error de extracción</strong>
            <p class="mb-0 mt-1" style="font-size:13.5px;">{{ $statement->extraccion_error }}</p>
        </div>
    </div>
@endif

{{-- Movements --}}
<div class="card-clean" data-reveal>
    <div class="card-clean__head">
        <strong>Movimientos</strong>
        <span class="text-muted" style="font-size:13px;">
            {{ $statement->movements->count() }} movimientos
            @if($statement->banco_perfil) · perfil: {{ $statement->banco_perfil }}@endif
        </span>
    </div>

    @if($statement->movements->isEmpty())
        <div class="empty-state">
            <i class="fa-solid fa-list"></i>
            <h3>Sin movimientos</h3>
            <p>La extracción no encontró movimientos en este documento.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Referencia</th>
                        <th class="text-end">Cargo</th>
                        <th class="text-end">Depósito</th>
                        <th class="text-end">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($statement->movements as $mov)
                        <tr>
                            <td class="data" style="font-size:13px; white-space:nowrap;">{{ $mov->fecha?->format('d/m/Y') }}</td>
                            <td style="font-size:13px;">{{ $mov->descripcion }}</td>
                            <td class="data text-muted" style="font-size:12px;">{{ $mov->referencia ?: '—' }}</td>
                            <td class="text-end data" style="font-size:13px; color:{{ $mov->cargo > 0 ? 'var(--warn)' : 'var(--text-muted)' }};">
                                {{ $mov->cargo > 0 ? '$' . number_format($mov->cargo, 2) : '—' }}
                            </td>
                            <td class="text-end data" style="font-size:13px; color:{{ $mov->deposito > 0 ? 'var(--ok)' : 'var(--text-muted)' }};">
                                {{ $mov->deposito > 0 ? '$' . number_format($mov->deposito, 2) : '—' }}
                            </td>
                            <td class="text-end data text-muted" style="font-size:13px;">
                                {{ $mov->saldo !== null ? '$' . number_format($mov->saldo, 2) : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const btn = document.getElementById('reextract-btn');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        await App.loading.button(btn, async () => {
            try {
                const res = await App.http.post('{{ route('statements.reextract', $statement) }}');
                App.toast.info(res.message || 'Reprocesando…');
                setTimeout(() => window.location.reload(), 1500);
            } catch (err) {
                App.toast.error(err.message || 'No se pudo reprocesar.');
            }
        });
    });
})();
</script>
@endpush
