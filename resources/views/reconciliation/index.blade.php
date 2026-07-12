@extends('layouts.app')
@section('title', 'Conciliación · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Conciliación</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reconciliation.questions') }}" class="btn btn-soft btn-icon">
            <i class="fa-solid fa-circle-question"></i> Preguntas al cliente
        </a>
        <button class="btn btn-brand btn-icon" id="run-btn">
            <i class="fa-solid fa-code-compare"></i> Ejecutar conciliación
        </button>
    </div>
</div>

{{-- Summary cards --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md" data-reveal>
        <div class="stat-card">
            <span class="stat-card__label"><i class="fa-solid fa-wand-magic-sparkles" style="color:var(--brand-500)"></i> Sugeridos</span>
            <span class="stat-card__value">{{ $counts['sugeridos'] }}</span>
        </div>
    </div>
    <div class="col-6 col-md" data-reveal data-reveal-delay="30">
        <div class="stat-card">
            <span class="stat-card__label"><i class="fa-solid fa-circle-check" style="color:var(--ok)"></i> Confirmados</span>
            <span class="stat-card__value">{{ $counts['confirmados'] }}</span>
        </div>
    </div>
    <div class="col-6 col-md" data-reveal data-reveal-delay="60">
        <div class="stat-card">
            <span class="stat-card__label"><i class="fa-solid fa-receipt" style="color:var(--warn)"></i> Sin factura</span>
            <span class="stat-card__value">{{ $counts['sinFactura'] }}</span>
        </div>
    </div>
    <div class="col-6 col-md" data-reveal data-reveal-delay="90">
        <div class="stat-card">
            <span class="stat-card__label"><i class="fa-solid fa-file-circle-xmark" style="color:var(--warn)"></i> Sin movimiento</span>
            <span class="stat-card__value">{{ $counts['sinMovimiento'] }}</span>
        </div>
    </div>
    <div class="col-6 col-md" data-reveal data-reveal-delay="120">
        <div class="stat-card">
            <span class="stat-card__label"><i class="fa-solid fa-calendar-xmark" style="color:var(--text-muted)"></i> Fuera de periodo</span>
            <span class="stat-card__value">{{ $counts['fueraPeriodo'] }}</span>
        </div>
    </div>
</div>

@if($matches->isEmpty() && $counts['sinFactura'] === 0 && $counts['sinMovimiento'] === 0)
    <div class="card-clean" data-reveal>
        <div class="empty-state">
            <i class="fa-solid fa-code-compare"></i>
            <h3>Sin conciliar</h3>
            <p>Ejecuta la conciliación para enlazar automáticamente los movimientos con las facturas del periodo.</p>
            <button class="btn btn-brand btn-icon mt-2" onclick="document.getElementById('run-btn').click()">
                <i class="fa-solid fa-code-compare"></i> Ejecutar conciliación
            </button>
        </div>
    </div>
@else
    {{-- Tabs --}}
    <ul class="nav-tabs-clean mb-3" data-reveal>
        <li class="nav-tab active" data-tab="matches">Enlaces <span class="tab-count">{{ $matches->count() }}</span></li>
        <li class="nav-tab" data-tab="sin-factura">Sin factura <span class="tab-count">{{ $counts['sinFactura'] }}</span></li>
        <li class="nav-tab" data-tab="sin-movimiento">Sin movimiento <span class="tab-count">{{ $counts['sinMovimiento'] }}</span></li>
        @if($counts['fueraPeriodo'])
            <li class="nav-tab" data-tab="fuera-periodo">Fuera de periodo <span class="tab-count">{{ $counts['fueraPeriodo'] }}</span></li>
        @endif
    </ul>

    {{-- Matches tab --}}
    <div class="tab-panel active" data-panel="matches">
        @include('reconciliation._matches')
    </div>

    {{-- Sin factura (bank movement, no invoice) --}}
    <div class="tab-panel" data-panel="sin-factura" style="display:none;">
        @include('reconciliation._sin_factura')
    </div>

    {{-- Sin movimiento (invoice, no bank movement) --}}
    <div class="tab-panel" data-panel="sin-movimiento" style="display:none;">
        @include('reconciliation._sin_movimiento')
    </div>

    {{-- Fuera de periodo --}}
    @if($counts['fueraPeriodo'])
        <div class="tab-panel" data-panel="fuera-periodo" style="display:none;">
            @include('reconciliation._fuera_periodo')
        </div>
    @endif
@endif

@endsection

@push('scripts')
<script>
(function () {
    // Run reconciliation
    const runBtn = document.getElementById('run-btn');
    runBtn?.addEventListener('click', async () => {
        await App.loading.button(runBtn, async () => {
            try {
                const res = await App.http.post('{{ route('reconciliation.run') }}');
                App.toast.success(res.message);
                setTimeout(() => window.location.reload(), 1200);
            } catch (err) {
                App.toast.error(err.message || 'No se pudo ejecutar la conciliación.');
            }
        });
    });

    // Tabs
    document.querySelectorAll('.nav-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
            tab.classList.add('active');
            document.querySelector(`[data-panel="${tab.dataset.tab}"]`).style.display = '';
        });
    });

    // Confirm / reject a suggested match
    document.querySelectorAll('[data-confirm-match]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.confirmMatch;
            await App.loading.button(btn, async () => {
                try {
                    await App.http.post(`{{ url('reconciliation/matches') }}/${id}/confirm`);
                    App.toast.success('Enlace confirmado.');
                    document.querySelector(`[data-match-row="${id}"]`)?.setAttribute('data-state', 'confirmado');
                    setTimeout(() => window.location.reload(), 700);
                } catch (e) { App.toast.error(e.message); }
            });
        });
    });
    document.querySelectorAll('[data-reject-match]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.rejectMatch;
            const ok = await App.modal.confirm({title:'Rechazar enlace', message:'El movimiento y la factura volverán a quedar sin conciliar.', confirmText:'Rechazar', danger:true});
            if (!ok) return;
            try {
                await App.http.post(`{{ url('reconciliation/matches') }}/${id}/reject`);
                App.toast.info('Enlace rechazado.');
                setTimeout(() => window.location.reload(), 600);
            } catch (e) { App.toast.error(e.message); }
        });
    });

    // Account assignment
    document.querySelectorAll('[data-account-select]').forEach(sel => {
        sel.addEventListener('change', async () => {
            const movId = sel.dataset.accountSelect;
            if (!sel.value) return;
            try {
                await App.http.post(`{{ url('reconciliation/movements') }}/${movId}/account`, { account_id: sel.value });
                App.toast.success('Cuenta asignada.');
            } catch (e) { App.toast.error(e.message); }
        });
    });

    // Manual link (from sin-factura): pick an invoice for a movement
    document.querySelectorAll('[data-link-select]').forEach(sel => {
        sel.addEventListener('change', async () => {
            const movId = sel.dataset.linkSelect;
            if (!sel.value) return;
            try {
                await App.http.post('{{ route('reconciliation.link') }}', { movement_id: movId, invoice_id: sel.value });
                App.toast.success('Enlazado manualmente.');
                setTimeout(() => window.location.reload(), 700);
            } catch (e) { App.toast.error(e.message); }
        });
    });
})();
</script>
@endpush
