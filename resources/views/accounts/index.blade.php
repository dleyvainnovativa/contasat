@extends('layouts.app')
@section('title', 'Catálogo de cuentas · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Catálogo de cuentas</h1>
        <div class="subtitle">{{ $client->display_name }}</div>
    </div>
    <div class="d-flex gap-2">
        {{-- Column selector --}}
        <div class="dropdown" style="position:relative;">
            <button class="btn btn-soft btn-icon" id="cols-btn" type="button" style="font-size:12.5px;">
                <i class="fa-solid fa-table-columns"></i> Columnas
            </button>
            <div class="cols-menu" id="cols-menu" style="display:none;">
                @foreach(['codigo_agrupador'=>'Agrupador','nivel'=>'Nivel','naturaleza'=>'Naturaleza','tipo'=>'Tipo','rfc'=>'RFC asociado'] as $col => $label)
                    <label class="cols-item">
                        <input type="checkbox" class="col-toggle" data-col="{{ $col }}" checked> {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>
        <button class="btn btn-brand btn-icon" data-create-account type="button">
            <i class="fa-solid fa-plus"></i> Crear cuenta
        </button>
    </div>
</div>

@if($accounts->isEmpty() && ! request()->hasAny(['q','tipo','naturaleza','estado','todas']))
    <div class="card-clean" data-reveal>
        <div class="empty-state">
            <i class="fa-solid fa-sitemap"></i>
            <h3>Sin cuentas del cliente</h3>
            <p>Este cliente aún no tiene cuentas propias.<br>
               El catálogo global está disponible — activa "Incluir catálogo global" para verlo,
               o crea una cuenta específica del cliente.</p>
            <button class="btn btn-brand btn-icon mt-2" data-create-account type="button">
                <i class="fa-solid fa-plus"></i> Crear cuenta
            </button>
        </div>
    </div>
@else
    <div class="card-clean" data-reveal>
        <div class="card-clean__head" style="gap:1rem; flex-wrap:wrap;">
            <form method="get" class="d-flex gap-2 align-items-center" style="flex:1; min-width:220px; flex-wrap:wrap;">
                <input type="hidden" name="per_page" value="{{ $perPage ?? 25 }}">
                <input type="search" name="q" value="{{ $q ?? '' }}" class="form-control"
                       placeholder="Buscar por número, nombre o agrupador…" style="max-width:300px;">

                <select name="tipo" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">Tipo: todas</option>
                    <option value="afectables"  @selected(($filters['tipo'] ?? '')==='afectables')>Afectables</option>
                    <option value="agrupadoras" @selected(($filters['tipo'] ?? '')==='agrupadoras')>Agrupadoras</option>
                </select>

                <select name="naturaleza" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">Naturaleza: todas</option>
                    <option value="D" @selected(($filters['naturaleza'] ?? '')==='D')>Deudora</option>
                    <option value="A" @selected(($filters['naturaleza'] ?? '')==='A')>Acreedora</option>
                </select>

                <select name="estado" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">Estado: todas</option>
                    <option value="activas"   @selected(($filters['estado'] ?? '')==='activas')>Activas</option>
                    <option value="inactivas" @selected(($filters['estado'] ?? '')==='inactivas')>Inactivas</option>
                </select>

                <select name="todas" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                    <option value="">Ámbito: propias</option>
                    <option value="1" @selected(!empty($filters['todas']))>Incluir catálogo global</option>
                </select>

                @if(($q ?? '') !== '' || ($filters['tipo'] ?? '') || ($filters['naturaleza'] ?? '') || ($filters['estado'] ?? '') || !empty($filters['todas']))
                    <a href="{{ route('accounts.index') }}" class="btn btn-soft btn-sm" title="Limpiar filtros">
                        <i class="fa-solid fa-xmark"></i> Limpiar
                    </a>
                @endif
            </form>
        </div>

        {{-- Bulk action bar — shown only when at least one row is selected. --}}
        <div id="bulk-bar" class="align-items-center gap-2"
             style="display:none; padding:.6rem 1rem; border-top:1px solid var(--border); background:var(--surface-2);">
            <span id="bulk-count" style="font-size:12.5px; font-weight:550;">0 seleccionadas</span>
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-brand" id="bulk-activate" style="padding:.3rem .6rem; font-size:12px;">
                    <i class="fa-solid fa-check"></i> Activar
                </button>
                <button type="button" class="btn btn-soft" id="bulk-inactivate" style="padding:.3rem .6rem; font-size:12px;">
                    <i class="fa-solid fa-ban"></i> Inactivar
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th class="text-center" style="width:36px;">
                            <input type="checkbox" id="acct-select-all" title="Seleccionar todas (esta página)">
                        </th>
                        <th>Número</th>
                        <th class="col-codigo_agrupador">Agrupador</th>
                        <th>Nombre</th>
                        <th class="col-nivel text-center">Nivel</th>
                        <th class="col-naturaleza text-center">Naturaleza</th>
                        <th class="col-tipo text-center">Tipo</th>
                        <th class="col-rfc">RFC asociado</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($accounts as $account)
                        @php $isActive = $account->effectiveActivo($overlay ?? []); @endphp
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="acct-check"
                                       data-id="{{ $account->id }}"
                                       data-numero="{{ $account->numero_cuenta }}">
                            </td>
                            <td class="data" style="font-weight:550;">
                                {{ $account->numero_cuenta }}
                                @if($account->client_id === null)
                                    <span class="badge-status s-secondary" style="font-size:9.5px; margin-left:.35rem;" title="Cuenta global">G</span>
                                @endif
                            </td>
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
                            <td class="col-rfc data text-muted" style="font-size:11.5px;">{{ $account->rfc_asociado ?: '—' }}</td>
                            <td class="text-center">
                                @if($isActive)
                                    <span class="badge-status s-success" style="font-size:11px;">Activa</span>
                                @else
                                    <span class="badge-status s-danger" style="font-size:11px;">Inactiva</span>
                                @endif
                            </td>
                            <td class="text-end" style="white-space:nowrap;">
                                @if($account->client_id === $client->id)
                                    <button class="btn btn-soft" style="padding:.3rem .5rem; font-size:12px;"
                                            data-edit-account
                                            data-id="{{ $account->id }}"
                                            data-nombre="{{ $account->nombre }}"
                                            data-rfc="{{ $account->rfc_asociado }}"
                                            data-numero="{{ $account->numero_cuenta }}"
                                            title="Editar nombre/RFC">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                @endif
                                <button class="btn {{ $isActive ? 'btn-soft' : 'btn-brand' }} acct-toggle"
                                        style="padding:.3rem .5rem; font-size:12px;"
                                        data-id="{{ $account->id }}"
                                        data-numero="{{ $account->numero_cuenta }}"
                                        data-activate="{{ $isActive ? '0' : '1' }}"
                                        title="{{ $isActive ? 'Inactivar (y sus subcuentas)' : 'Activar' }}">
                                    <i class="fa-solid {{ $isActive ? 'fa-ban' : 'fa-check' }}"></i>
                                    {{ $isActive ? 'Inactivar' : 'Activar' }}
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted" style="padding:1.5rem;">
                                Sin resultados para los filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <label class="text-muted" style="font-size:12.5px;">Mostrar</label>
            <select class="form-select form-select-sm" style="width:auto;" onchange="
                const u = new window.URL(window.location.href);
                u.searchParams.set('per_page', this.value);
                u.searchParams.delete('page');
                window.location = u.toString();
            ">
                @foreach([25, 100, 200, 500] as $opt)
                    <option value="{{ $opt }}" @selected(($perPage ?? 25) === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
            <span class="text-muted" style="font-size:12.5px;">por página</span>
        </div>
        <div>{{ $accounts->links() }}</div>
    </div>
@endif

@php $storeRoute = route('accounts.store'); @endphp
@include('partials.create_account_modal', ['storeRoute' => $storeRoute, 'parentOptions' => $parentOptions ?? []])
@include('partials.edit_account_modal')
@endsection

@push('scripts')
<script>
(function () {
    // ---- Column selector (server-persisted) ----
    const PREF_KEY = 'accounts_columns';
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

    // ---- Row selection + bulk activate / inactivate ----
    const selectAll = document.getElementById('acct-select-all');
    const bulkBar   = document.getElementById('bulk-bar');
    const bulkCount = document.getElementById('bulk-count');
    const checks    = () => Array.from(document.querySelectorAll('.acct-check'));
    const checked   = () => checks().filter(c => c.checked);

    function refreshBulk() {
        const all = checks();
        const n = checked().length;
        if (bulkCount) bulkCount.textContent = n + (n === 1 ? ' seleccionada' : ' seleccionadas');
        if (bulkBar) bulkBar.style.display = n > 0 ? 'flex' : 'none';
        if (selectAll) {
            selectAll.checked = all.length > 0 && n === all.length;
            selectAll.indeterminate = n > 0 && n < all.length;
        }
    }

    selectAll?.addEventListener('change', () => {
        checks().forEach(c => c.checked = selectAll.checked);
        refreshBulk();
    });
    checks().forEach(c => c.addEventListener('change', refreshBulk));

    async function bulkToggle(activate) {
        const items = checked();
        if (!items.length) return;
        const ids = items.map(c => c.dataset.id);

        const ok = await App.modal.confirm({
            title: activate ? 'Activar cuentas' : 'Inactivar cuentas',
            message: activate
                ? '¿Activar ' + ids.length + ' cuenta(s) seleccionada(s)?'
                : '¿Inactivar ' + ids.length + ' cuenta(s) y sus subcuentas? Si una cuenta (o alguna de sus subcuentas) tiene movimientos, esa rama no se inactivará.',
            confirmText: activate ? 'Activar' : 'Inactivar',
            danger: !activate,
        });
        if (!ok) return;

        const btn = document.getElementById(activate ? 'bulk-activate' : 'bulk-inactivate');
        await App.loading.button(btn, async () => {
            try {
                const res = await App.http.post('{{ route('accounts.bulk-toggle') }}', { ids, activo: activate });
                (res.blocked && res.blocked.length) ? App.toast.warning(res.message) : App.toast.success(res.message);
                setTimeout(() => window.location.reload(), 900);
            } catch (e) { App.toast.error(e.message); }
        });
    }

    document.getElementById('bulk-activate')?.addEventListener('click', () => bulkToggle(true));
    document.getElementById('bulk-inactivate')?.addEventListener('click', () => bulkToggle(false));
    refreshBulk();

    // ---- Single-row activate / inactivate (per client) ----
    document.querySelectorAll('.acct-toggle').forEach(btn => {
        btn.addEventListener('click', async () => {
            const activate = btn.dataset.activate === '1';
            if (!activate) {
                const ok = await App.modal.confirm({
                    title: 'Inactivar cuenta',
                    message: '¿Inactivar la cuenta ' + btn.dataset.numero
                        + ' y sus subcuentas? Si la cuenta o alguna subcuenta tiene movimientos, no se inactivará ninguna.',
                    confirmText: 'Inactivar',
                    danger: true,
                });
                if (!ok) return;
            }
            await App.loading.button(btn, async () => {
                try {
                    const res = await App.http.post('{{ url('accounts') }}/' + btn.dataset.id + '/toggle', { activo: activate });
                    const blocked = res.result && res.result.blocked && res.result.blocked.length;
                    blocked ? App.toast.warning(res.message) : App.toast.success(res.message);
                    setTimeout(() => window.location.reload(), 900);
                } catch (e) { App.toast.error(e.message); }
            });
        });
    });
})();
</script>
@endpush
