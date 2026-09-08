@extends('layouts.app')
@section('title', 'Asientos contables · ContaSAT')

@section('content')
<style>
    .as-wrap {
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: auto;
        background: var(--surface);
    }

    .as-table {
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
        width: 100%;
    }

    .as-table th,
    .as-table td {
        padding: .6rem .75rem;
        border-bottom: 1px solid var(--border);
        text-align: left;
        white-space: nowrap;
    }

    .as-table thead th {
        background: var(--surface-2);
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .02em;
        color: var(--text-muted);
    }

    .as-table tbody tr {
        cursor: pointer;
    }

    .as-table tbody tr:hover {
        background: var(--surface-2);
    }

    .as-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .as-muted {
        color: var(--text-muted);
    }

    .as-foot td {
        font-weight: 700;
        border-top: 2px solid var(--border);
        background: var(--surface-2);
    }

    .as-detail td {
        background: var(--surface-2);
        padding: 0;
    }

    .as-detail table {
        width: 100%;
        font-size: 12px;
    }

    .as-detail table td {
        border-bottom: 1px solid var(--border);
        padding: .4rem .75rem;
    }
</style>

<div class="page-head" data-reveal>
    <div>
        <h1>Asientos contables</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2" data-reveal>
    <form method="GET" class="d-flex align-items-center gap-2">
        <label class="as-muted" style="font-size:12.5px;">Tipo</label>
        <select name="tipo" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
            <option value="">Todos</option>
            @foreach($tipoLabels as $key => $label)
            <option value="{{ $key }}" @selected($tipo===$key)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
    <div class="as-muted" style="font-size:12px;">{{ $totals['count'] }} pólizas</div>
</div>

<div class="as-wrap" data-reveal>
    <table class="as-table">
        <thead>
            <tr>
                <th>Núm.</th>
                <th>Tipo</th>
                <th>Factura</th>
                <th>Contraparte</th>
                <th>Fecha</th>
                <th class="as-num">Cargo</th>
                <th class="as-num">Abono</th>
                <th>Cuadra</th>
            </tr>
        </thead>
        <tbody>
            @forelse($polizas as $pol)
            @php
            $inv = $pol->invoice;
            $isEmitida = $inv && $inv->tipo === 'emitida';
            $contraparte = $inv
            ? ($isEmitida ? ($inv->receptor_nombre ?: $inv->receptor_rfc) : ($inv->emisor_nombre ?: $inv->emisor_rfc))
            : '—';
            @endphp
            <tr data-poliza="{{ $pol->id }}">
                <td class="data" style="font-weight:600;">{{ $pol->num_iden }}</td>
                <td>{{ $pol->tipoLabel() }}</td>
                <td class="data">{{ $inv ? ($inv->serie . $inv->folio) : '—' }}</td>
                <td style="max-width:240px; overflow:hidden; text-overflow:ellipsis;" title="{{ $contraparte }}">{{ $contraparte }}</td>
                <td class="data">{{ optional($pol->fecha)->format('Y-m-d') }}</td>
                <td class="as-num">${{ number_format($pol->total_cargo, 2) }}</td>
                <td class="as-num">${{ number_format($pol->total_abono, 2) }}</td>
                <td>
                    @if($pol->cuadra)
                    <span class="badge-status s-success" style="font-size:10.5px;"><i class="fa-solid fa-check"></i> Cuadra</span>
                    @else
                    <span class="badge-status s-danger" style="font-size:10.5px;"><i class="fa-solid fa-triangle-exclamation"></i> Descuadre</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="as-muted" style="text-align:center; padding:2rem;">
                    No hay asientos generados en este periodo.
                </td>
            </tr>
            @endforelse
        </tbody>
        @if($polizas->isNotEmpty())
        <tfoot>
            <tr class="as-foot">
                <td colspan="5">Totales ({{ $totals['count'] }} pólizas)</td>
                <td class="as-num">${{ number_format($totals['cargo'], 2) }}</td>
                <td class="as-num">${{ number_format($totals['abono'], 2) }}</td>
                <td></td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>

<div class="mt-3">{{ $polizas->links() }}</div>
@endsection

@push('scripts')
<script type="module">
    (function() {
        const base = @json(url('asientos'));

        // Click a row to expand/collapse its asiento detail (cargo/abono lines).
        document.querySelectorAll('tr[data-poliza]').forEach(row => {
            row.addEventListener('click', async () => {
                const id = row.dataset.poliza;
                const next = row.nextElementSibling;

                // Collapse if already expanded.
                if (next && next.classList.contains('as-detail')) {
                    next.remove();
                    return;
                }
                // Remove any other open detail (one at a time).
                document.querySelectorAll('.as-detail').forEach(d => d.remove());

                try {
                    const d = await App.http.get(`${base}/${id}`);
                    const rows = d.lines.map(l => `
                        <tr>
                            <td class="data">${l.numero_cuenta || '—'}</td>
                            <td>${l.nombre_cuenta || ''}</td>
                            <td class="as-muted">${l.concepto || ''}</td>
                            <td class="as-num">${l.cargo ? '$' + l.cargo.toLocaleString('es-MX',{minimumFractionDigits:2}) : ''}</td>
                            <td class="as-num">${l.abono ? '$' + l.abono.toLocaleString('es-MX',{minimumFractionDigits:2}) : ''}</td>
                        </tr>`).join('');

                    const detail = document.createElement('tr');
                    detail.className = 'as-detail';
                    detail.innerHTML = `<td colspan="8"><table>
                        <thead><tr>
                            <th style="padding:.4rem .75rem;">Cuenta</th><th>Nombre</th><th>Concepto</th>
                            <th class="as-num">Cargo</th><th class="as-num">Abono</th>
                        </tr></thead>
                        <tbody>${rows}</tbody></table></td>`;
                    row.after(detail);
                } catch (e) {
                    App.toast.error(e.message);
                }
            });
        });
    })();
</script>
@endpush