@extends('layouts.app')
@section('title', 'Catálogo de cuentas · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Catálogo de cuentas</h1>
        <div class="subtitle">{{ $client->display_name }}</div>
    </div>
    <button class="btn btn-brand btn-icon" data-import-catalog>
        <i class="fa-solid fa-file-import"></i> Importar catálogo SAT
    </button>
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

@if($counts['total'] === 0)
    <div class="card-clean" data-reveal>
        <div class="empty-state">
            <i class="fa-solid fa-sitemap"></i>
            <h3>Sin catálogo</h3>
            <p>Importa el catálogo de código agrupador del SAT para este cliente.<br>
               Acepta el archivo xlsx o csv tal como lo descargas del SAT.</p>
            <button class="btn btn-brand btn-icon mt-2" data-import-catalog>
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
                        <th>Número</th><th>Agrupador</th><th>Nombre</th>
                        <th class="text-center">Nivel</th><th class="text-center">Naturaleza</th>
                        <th class="text-center">Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                        <tr>
                            <td class="data" style="font-weight:550;">{{ $account->numero_cuenta }}</td>
                            <td class="data text-muted">{{ $account->codigo_agrupador }}</td>
                            <td>
                                {{ $account->nombre }}
                                @if($account->rfc_asociado)
                                    <span class="data text-muted" style="font-size:11px;">· {{ $account->rfc_asociado }}</span>
                                @endif
                            </td>
                            <td class="text-center data">{{ $account->nivel }}</td>
                            <td class="text-center">
                                <span class="badge-status s-{{ $account->naturaleza === 'D' ? 'info' : 'secondary' }}" style="font-size:11px;">
                                    {{ $account->naturaleza === 'D' ? 'Deudora' : 'Acreedora' }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($account->auto_generada)
                                    <span class="badge-status s-warning" style="font-size:11px;"><i class="fa-solid fa-robot"></i> Auto</span>
                                @elseif($account->es_afectable)
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
                <h5 class="mb-1" style="font-weight:600;">Importar catálogo SAT</h5>
                <p class="text-muted mb-3" style="font-size:13px;">
                    Sube el archivo de código agrupador del SAT (xlsx o csv) con las columnas
                    <span class="data">Nivel · Código agrupador · Nombre</span>.
                </p>
                <div class="mb-3">
                    <input type="file" id="catalog-file" class="form-control" accept=".xlsx,.xls,.csv">
                </div>
                <div class="form-hint">
                    <i class="fa-solid fa-circle-info"></i>
                    Las cuentas existentes se actualizan; las subcuentas auto-generadas por RFC no se tocan.
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand btn-icon" id="catalog-submit"><i class="fa-solid fa-check"></i> Importar</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
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
                const res = await App.http.post('{{ route('accounts.import') }}', fd);
                App.toast.success(res.message);
                App.modal.hide('import-modal');
                setTimeout(() => window.location.reload(), 1200);
            } catch (e) { App.toast.error(e.message); }
        });
    });
})();
</script>
@endpush
