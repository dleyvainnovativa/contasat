@extends('layouts.app')
@section('title', 'Catálogo global · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Catálogo global</h1>
        <div class="subtitle">Código agrupador SAT · compartido por todos los clientes</div>
    </div>
    <div class="d-flex gap-2">
        {{-- Column selector --}}
        <div class="dropdown" style="position:relative;">
            <button class="btn btn-soft btn-icon" id="cols-btn" type="button" style="font-size:12.5px;">
                <i class="fa-solid fa-table-columns"></i> Columnas
            </button>
            <div class="cols-menu" id="cols-menu" style="display:none;">
                @foreach(['codigo_agrupador'=>'Agrupador','nivel'=>'Nivel','naturaleza'=>'Naturaleza','tipo'=>'Tipo'] as $col => $label)
                    <label class="cols-item">
                        <input type="checkbox" class="col-toggle" data-col="{{ $col }}" checked> {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>
        <button class="btn btn-soft btn-icon" data-create-account type="button">
            <i class="fa-solid fa-plus"></i> Crear cuenta
        </button>
        <button class="btn btn-brand btn-icon" data-import-catalog type="button">
            <i class="fa-solid fa-file-import"></i> Importar catálogo SAT
        </button>
    </div>
</div>

<div class="card-clean mb-4" data-reveal style="border-left:3px solid var(--brand-500);">
    <div class="card-clean__body">
        <div class="d-flex align-items-start gap-3" style="font-size:13.5px;">
            <i class="fa-solid fa-circle-info" style="color:var(--brand-500); margin-top:2px;"></i>
            <div>
                Este catálogo es <strong>global</strong>: se importa una sola vez y lo usan todos los clientes.
                Las subcuentas de clientes (105.01.###, 105.02.###) se crean automáticamente por RFC y aparecen
                en el catálogo de cada cliente, no aquí.
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4" data-reveal>
    <div class="col-6">
        <div class="stat-card">
            <div class="stat-card__value">{{ number_format($counts['total']) }}</div>
            <div class="stat-card__label">Cuentas globales</div>
        </div>
    </div>
    <div class="col-6">
        <div class="stat-card">
            <div class="stat-card__value">{{ number_format($counts['afectables']) }}</div>
            <div class="stat-card__label">Afectables</div>
        </div>
    </div>
</div>

@if($counts['total'] === 0)
    <div class="card-clean" data-reveal>
        <div class="empty-state">
            <i class="fa-solid fa-sitemap"></i>
            <h3>Sin catálogo</h3>
            <p>Importa el catálogo de código agrupador del SAT.<br>
               Se comparte con todos los clientes; solo hay que hacerlo una vez.</p>
            <button class="btn btn-brand btn-icon mt-2" data-import-catalog type="button">
                <i class="fa-solid fa-file-import"></i> Importar catálogo SAT
            </button>
        </div>
    </div>
@else
    <div class="card-clean" data-reveal>
        <div class="card-clean__head" style="gap:1rem; flex-wrap:wrap;">
            <form method="get" class="d-flex gap-2" style="flex:1; min-width:220px;">
                <input type="search" name="q" value="{{ $q }}" class="form-control"
                       placeholder="Buscar por número, nombre o agrupador…" style="max-width:340px;">
                <label class="d-flex align-items-center gap-2" style="font-size:13px; white-space:nowrap;">
                    <input type="checkbox" name="solo_afectables" value="1"
                           {{ request()->boolean('solo_afectables') ? 'checked' : '' }}
                           onchange="this.form.submit()">
                    Solo afectables
                </label>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th class="col-codigo_agrupador">Agrupador</th>
                        <th>Nombre</th>
                        <th class="col-nivel text-center">Nivel</th>
                        <th class="col-naturaleza text-center">Naturaleza</th>
                        <th class="col-tipo text-center">Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                        <tr>
                            <td class="data" style="font-weight:550;">{{ $account->numero_cuenta }}</td>
                            <td class="col-codigo_agrupador data text-muted">{{ $account->codigo_agrupador }}</td>
                            <td>{{ $account->nombre }}</td>
                            <td class="col-nivel text-center data">{{ $account->nivel }}</td>
                            <td class="col-naturaleza text-center">
                                <span class="badge-status s-{{ $account->naturaleza === 'D' ? 'info' : 'secondary' }}" style="font-size:11px;">
                                    {{ $account->naturaleza === 'D' ? 'Deudora' : 'Acreedora' }}
                                </span>
                            </td>
                            <td class="col-tipo text-center">
                                @if($account->es_afectable)
                                    <span class="badge-status s-success" style="font-size:11px;">Afectable</span>
                                @else
                                    <span class="badge-status s-secondary" style="font-size:11px;">Agrupador</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $accounts->links() }}</div>
@endif

{{-- Import modal --}}
<div class="modal fade" id="import-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg); border-color:var(--border); background:var(--surface);">
            <div class="modal-body p-4">
                <h5 class="mb-1" style="font-weight:600;">Importar catálogo global SAT</h5>
                <p class="text-muted mb-3" style="font-size:13px;">
                    Sube el archivo de código agrupador del SAT (xlsx o csv) con las columnas
                    <span class="data">Nivel · Código agrupador · Nombre</span>.
                </p>
                <div class="mb-3">
                    <input type="file" id="catalog-file" class="form-control" accept=".xlsx,.xls,.csv">
                </div>
                <div class="form-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    Se comparte con todos los clientes. Reimportar actualiza en lugar de duplicar.
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand btn-icon" id="catalog-submit"><i class="fa-solid fa-check"></i> Importar</button>
                </div>
            </div>
        </div>
    </div>
</div>

@php $storeRoute = route('catalog.store'); @endphp
@include('partials.create_account_modal', ['storeRoute' => $storeRoute, 'parentOptions' => $parentOptions ?? []])
@endsection

@push('scripts')
<script>
(function () {
    // ---- Import ----
    document.querySelectorAll('[data-import-catalog]').forEach(b =>
        b.addEventListener('click', () => App.modal.show('import-modal')));

    const btn = document.getElementById('catalog-submit');
    btn?.addEventListener('click', async () => {
        const file = document.getElementById('catalog-file').files[0];
        if (!file) { App.toast.warning('Selecciona un archivo.'); return; }
        const fd = new FormData();
        fd.append('archivo', file);
        await App.loading.button(btn, async () => {
            try {
                const res = await App.http.post('{{ route('catalog.import') }}', fd);
                App.toast.success(res.message);
                App.modal.hide('import-modal');
                setTimeout(() => window.location.reload(), 1200);
            } catch (e) { App.toast.error(e.message); }
        });
    });

    // ---- Column selector (server-persisted) ----
    const PREF_KEY = 'global_accounts_columns';
    const hidden = @json($hiddenColumns ?? []);

    hidden.forEach(col => {
        document.querySelectorAll('.col-' + col).forEach(el => el.style.display = 'none');
        const cb = document.querySelector(`.col-toggle[data-col="${col}"]`);
        if (cb) cb.checked = false;
    });

    const colsBtn = document.getElementById('cols-btn');
    const colsMenu = document.getElementById('cols-menu');
    colsBtn?.addEventListener('click', () => {
        colsMenu.style.display = colsMenu.style.display === 'none' ? 'block' : 'none';
    });
    document.addEventListener('click', (e) => {
        if (colsBtn && !colsBtn.contains(e.target) && !colsMenu.contains(e.target)) colsMenu.style.display = 'none';
    });

    document.querySelectorAll('.col-toggle').forEach(cb => {
        cb.addEventListener('change', async () => {
            const col = cb.dataset.col;
            document.querySelectorAll('.col-' + col).forEach(el => el.style.display = cb.checked ? '' : 'none');
            const nowHidden = Array.from(document.querySelectorAll('.col-toggle'))
                .filter(c => !c.checked).map(c => c.dataset.col);
            try {
                await App.http.post('{{ route('preferences.update') }}', { key: PREF_KEY, value: nowHidden });
            } catch (e) { /* non-fatal */ }
        });
    });
})();
</script>
@endpush