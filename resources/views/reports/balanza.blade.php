@extends('layouts.app')
@section('title', 'Balanza de comprobación · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Balanza de comprobación</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('reports.index') }}" class="btn btn-soft btn-icon">
            <i class="fa-solid fa-arrow-left"></i> Reportes
        </a>
        <a href="{{ route('reports.balanza.export') }}" class="btn btn-brand btn-icon">
            <i class="fa-solid fa-file-excel"></i> Exportar
        </a>
    </div>
</div>

{{-- Headline cards --}}
<div class="row g-3 mb-4" data-reveal>
    @foreach([
        ['Activos', $cards['activos'], 'var(--ok)'],
        ['Pasivos', $cards['pasivos'], 'var(--text)'],
        ['Capital', $cards['capital'], 'var(--text)'],
        ['Ingresos', $cards['ingresos'], 'var(--ok)'],
        ['Egresos', $cards['egresos'], 'var(--warn)'],
        ['Remanente o utilidad', $cards['remanente'], $cards['remanente'] >= 0 ? 'var(--ok)' : 'var(--danger)'],
    ] as [$label, $value, $color])
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-card__label">{{ $label }}</div>
                <div class="stat-card__value" style="color:{{ $color }};">${{ number_format($value, 2) }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="card-clean" data-reveal>
    <div class="card-clean__head" style="gap:1rem; flex-wrap:wrap;">
        <form method="get" class="d-flex gap-2 align-items-center" style="flex:1; min-width:220px; flex-wrap:wrap;">
            <input type="hidden" name="per_page" value="{{ $perPage ?? 100 }}">
            <input type="search" name="q" value="{{ $q ?? '' }}" class="form-control"
                   placeholder="Buscar por cuenta o ID contable…" style="max-width:320px;">
            <label class="d-flex align-items-center gap-2" style="font-size:12.5px; cursor:pointer;">
                <input type="checkbox" name="mostrar" value="1" @checked($mostrar) onchange="this.form.submit()">
                Mostrar cuentas sin movimientos
            </label>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table-clean">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Naturaleza</th>
                    <th>Cuenta</th>
                    <th>ID Contable</th>
                    <th class="text-end">Saldo inicial</th>
                    <th class="text-end">Cargos</th>
                    <th class="text-end">Abonos</th>
                    <th class="text-end">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row['tipo'] }}</td>
                        <td>
                            <span class="badge-status s-{{ $row['naturaleza'] === 'Deudora' ? 'info' : 'secondary' }}" style="font-size:11px;">
                                {{ $row['naturaleza'] }}
                            </span>
                        </td>
                        <td>{{ $row['nombre'] }}</td>
                        <td class="data text-muted">{{ $row['numero_cuenta'] }}</td>
                        <td class="text-end data">${{ number_format($row['saldo_inicial'], 2) }}</td>
                        <td class="text-end data">${{ number_format($row['cargos'], 2) }}</td>
                        <td class="text-end data">${{ number_format($row['abonos'], 2) }}</td>
                        <td class="text-end data" style="font-weight:600;">${{ number_format($row['saldo'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding:1.5rem;">
                            Sin cuentas con movimientos en {{ $period->label }}.
                            @unless($mostrar) Marca "Mostrar cuentas sin movimientos" para ver todas. @endunless
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
                <option value="{{ $opt }}" @selected(($perPage ?? 100) === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
        <span class="text-muted" style="font-size:12.5px;">por página</span>
    </div>
    <div>{{ $rows->links() }}</div>
</div>
@endsection
