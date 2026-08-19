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

<div class="row g-3 mb-4" data-reveal>
    <div class="col-4">
        <div class="stat-card">
            <div class="stat-card__value">{{ number_format($counts['total']) }}</div>
            <div class="stat-card__label">Cuentas totales</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card">
            <div class="stat-card__value">{{ number_format($counts['afectables']) }}</div>
            <div class="stat-card__label">Afectables</div>
        </div>
    </div>
    <div class="col-4">
        <div class="stat-card">
            <div class="stat-card__value">{{ number_format($counts['auto']) }}</div>
            <div class="stat-card__label">Subcuentas auto</div>
        </div>
    </div>
</div>

@if($accounts->isEmpty() && ! request()->has('q'))
    <div class="card-clean" data-reveal>
        <div class="empty-state">
            <i class="fa-solid fa-sitemap"></i>
            <h3>Sin cuentas del cliente</h3>
            <p>Este cliente aún no tiene cuentas propias.<br>
               El catálogo global está disponible — activa "Mostrar catálogo global" para verlo,
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
                <input type="search" name="q" value="{{ $q ?? '' }}" class="form-control"
                       placeholder="Buscar por número, nombre o agrupador…" style="max-width:320px;">
                <label class="d-flex align-items-center gap-2" style="font-size:13px; white-space:nowrap;">
                    <input type="checkbox" name="solo_afectables" value="1"
                           {{ request()->boolean('solo_afectables') ? 'checked' : '' }}
                           onchange="this.form.submit()">
                    Solo afectables
                </label>
                {{-- Default: only the client's own accounts. Opt IN to also see the
                     global catalog. Absence of the param = client-only (default). --}}
                <label class="d-flex align-items-center gap-2" style="font-size:13px; white-space:nowrap;">
                    <input type="checkbox" name="todas" value="1"
                           {{ request()->has('todas') ? 'checked' : '' }}
                           onchange="this.form.submit()">
                    Mostrar catálogo global
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
                        <th class="col-rfc">RFC asociado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                        <tr>
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
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $accounts->links() }}</div>
@endif

@php $storeRoute = route('accounts.store'); @endphp
@include('partials.create_account_modal', ['storeRoute' => $storeRoute, 'parentOptions' => $parentOptions ?? []])
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
})();
</script>
@endpush