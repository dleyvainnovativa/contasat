@extends('layouts.app')
@section('title', 'Calendario de actividades · ContaSAT')

@section('content')
<style>
    .cal-dot { display:inline-block; width:11px; height:11px; border-radius:50%; margin-right:.35rem; vertical-align:middle; }
    .cal-dot--realizada  { background: var(--ok); }
    .cal-dot--en_proceso { background: var(--warn); }
    .cal-dot--pendiente  { background: var(--danger); }
    .cal-dot--no_aplica  { background: var(--neutral); }

    .cal-board { border:1px solid var(--border); border-radius:var(--radius-lg); overflow:hidden; background:var(--surface); }
    .cal-group-head {
        padding:.5rem 1rem; font-size:12px; font-weight:600; text-transform:uppercase;
        letter-spacing:.04em; color:var(--text-muted); background:var(--surface-2);
        border-top:1px solid var(--border);
    }
    .cal-board .cal-group-head:first-child { border-top:0; }
    .cal-row {
        display:flex; align-items:center; gap:1rem; padding:.85rem 1rem;
        border-top:1px solid var(--border);
    }
    .cal-board > .cal-row:first-child { border-top:0; }
    .cal-row--grouped { padding-left:1.75rem; }
    .cal-row__status { flex:0 0 auto; }
    .cal-row__label { flex:1 1 auto; display:flex; align-items:center; gap:.5rem; font-size:14px; }
    .cal-row__action { flex:0 0 auto; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; justify-content:flex-end; }

    .cal-tag {
        font-size:10.5px; color:var(--brand-600); background:var(--brand-050);
        padding:.1rem .4rem; border-radius:var(--radius-sm); font-weight:600;
    }
    [data-theme="dark"] .cal-tag { background:var(--surface-2); color:var(--brand-500); }

    .cal-toggle { display:inline-flex; align-items:center; gap:.35rem; font-size:12px; color:var(--text-muted); cursor:pointer; margin:0; }
    .cal-toggle input { accent-color: var(--brand-500); }

    @media (max-width: 640px) {
        .cal-row { flex-wrap:wrap; }
        .cal-row__action { width:100%; justify-content:flex-start; }
    }
</style>

<div class="page-head" data-reveal>
    <div>
        <h1>Calendario de actividades</h1>
        <div class="subtitle">{{ $client->display_name }} · {{ $period->label }}</div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge-status s-success" style="font-size:13px;">
            <i class="fa-solid fa-circle-check"></i>
            <span id="cal-done">{{ $doneCount }}</span>/{{ $totalCount }} realizadas
        </span>
    </div>
</div>

{{-- Legend --}}
<div class="d-flex flex-wrap gap-3 mb-4" data-reveal style="font-size:12.5px; color:var(--text-muted);">
    <span><span class="cal-dot cal-dot--realizada"></span> Realizada</span>
    <span><span class="cal-dot cal-dot--en_proceso"></span> En proceso</span>
    <span><span class="cal-dot cal-dot--pendiente"></span> Pendiente</span>
    <span><span class="cal-dot cal-dot--no_aplica"></span> No aplica</span>
</div>

@php
    // Group consecutive activities that share a group label (Estado de cuenta).
    $rendered = [];
@endphp

<div class="cal-board" data-reveal>
    @foreach($activities as $act)
        @php
            $group = $act['group'];
            // Print a group header once, before the first row of that group.
            $printHeader = $group && ! in_array($group, $rendered, true);
            if ($printHeader) { $rendered[] = $group; }
        @endphp

        @if($printHeader)
        <div class="cal-group-head">{{ $group }}</div>
        @endif

        <div class="cal-row {{ $group ? 'cal-row--grouped' : '' }}" data-activity="{{ $act['key'] }}">
            <div class="cal-row__status">
                <span class="cal-dot cal-dot--{{ $act['status'] }}" data-status-dot></span>
            </div>

            <div class="cal-row__label">
                <span>{{ $act['label'] }}</span>
                @if($act['mode'] === 'auto')
                <span class="cal-tag" title="Estado automático"><i class="fa-solid fa-bolt"></i> Auto</span>
                @endif
            </div>

            <div class="cal-row__action">
                {{-- SAT link-out (32D / Constancia) --}}
                @if($act['sat_url'])
                <a href="{{ $act['sat_url'] }}" target="_blank" rel="noopener"
                    class="btn btn-soft btn-sm btn-icon">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Abrir en SAT
                </a>
                @endif

                {{-- Deep-link when an auto activity is en proceso --}}
                @if($act['deep_link'])
                <a href="{{ $act['deep_link'] }}" class="btn btn-soft btn-sm btn-icon">
                    <i class="fa-solid fa-arrow-right"></i> Ir al pendiente
                </a>
                @endif

                {{-- Manual status control (manual activities only, and only when it applies) --}}
                @if($act['mode'] === 'manual')
                <select class="form-select form-select-sm cal-status-select" data-status-select
                    style="width:auto; min-width:150px;" {{ $act['status'] === 'no_aplica' ? 'disabled' : '' }}>
                    <option value="" {{ ! in_array($act['status'], ['realizada','en_proceso','pendiente'], true) ? 'selected' : '' }}>— Sin tag —</option>
                    <option value="realizada"  {{ $act['status'] === 'realizada'  ? 'selected' : '' }}>Realizada</option>
                    <option value="en_proceso" {{ $act['status'] === 'en_proceso' ? 'selected' : '' }}>En proceso</option>
                    <option value="pendiente"  {{ $act['status'] === 'pendiente'  ? 'selected' : '' }}>Pendiente</option>
                </select>
                @endif

                {{-- No aplica toggle (every activity) --}}
                <label class="cal-toggle" title="No aplica para este cliente/periodo">
                    <input type="checkbox" data-toggle-enabled {{ $act['status'] === 'no_aplica' ? '' : 'checked' }}>
                    <span>Aplica</span>
                </label>
            </div>
        </div>
    @endforeach
</div>
@endsection

@push('scripts')
<script type="module">
    (function () {
        const base = @json(url('calendario'));

        const dotClass = {
            realizada: 'cal-dot--realizada',
            en_proceso: 'cal-dot--en_proceso',
            pendiente: 'cal-dot--pendiente',
            no_aplica: 'cal-dot--no_aplica',
        };

        function setDot(row, status) {
            const dot = row.querySelector('[data-status-dot]');
            dot.className = 'cal-dot ' + (dotClass[status] || 'cal-dot--pendiente');
        }

        function refreshDoneCount() {
            const total = document.querySelectorAll('.cal-row').length;
            let done = 0;
            document.querySelectorAll('[data-status-dot]').forEach(d => {
                if (d.classList.contains('cal-dot--realizada')) done++;
            });
            const el = document.getElementById('cal-done');
            if (el) el.textContent = done;
            return { done, total };
        }

        // Manual status tag change
        document.querySelectorAll('[data-status-select]').forEach(sel => {
            sel.addEventListener('change', async () => {
                const row = sel.closest('.cal-row');
                const key = row.dataset.activity;
                const value = sel.value || null;
                try {
                    const res = await App.http.put(`${base}/${key}/status`, { manual_status: value });
                    // Reflect the new status on the dot. Empty tag on a manual activity
                    // falls back to "pendiente" (manual activities have no auto status).
                    setDot(row, value || 'pendiente');
                    refreshDoneCount();
                    App.toast.success(res.message);
                } catch (e) {
                    App.toast.error(e.message);
                }
            });
        });

        // No aplica toggle
        document.querySelectorAll('[data-toggle-enabled]').forEach(chk => {
            chk.addEventListener('change', async () => {
                const row = chk.closest('.cal-row');
                const key = row.dataset.activity;
                const enabled = chk.checked;
                try {
                    const res = await App.http.put(`${base}/${key}/toggle`, { enabled });
                    const sel = row.querySelector('[data-status-select]');
                    if (!enabled) {
                        setDot(row, 'no_aplica');
                        if (sel) { sel.disabled = true; sel.value = ''; }
                    } else {
                        // Re-enabling: manual rows return to their tag (or pendiente);
                        // auto rows will reflect their computed status on next full load.
                        if (sel) sel.disabled = false;
                        setDot(row, (sel && sel.value) ? sel.value : 'pendiente');
                    }
                    refreshDoneCount();
                    App.toast.success(res.message);
                } catch (e) {
                    App.toast.error(e.message);
                    chk.checked = !enabled; // revert on failure
                }
            });
        });
    })();
</script>
@endpush
