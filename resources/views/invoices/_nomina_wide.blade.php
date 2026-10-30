{{-- Wide nómina table (tipo N). Invoice-level only — the nómina complement
     (percepciones/deducciones) is not parsed. Contraparte is the employee
     (receptor); the contable account is fixed to 601.01 by classification.
     Expects the same $invoices / $q / $perPage the other wide tables receive. --}}
<style>
    .pi-wrap { border:1px solid var(--border); border-radius:var(--radius-lg); overflow:auto; background:var(--surface); max-height:72vh; }
    .pi-table { border-collapse:separate; border-spacing:0; font-size:12px; white-space:nowrap; min-width:100%; }
    .pi-table th, .pi-table td { padding:.5rem .6rem; border-bottom:1px solid var(--border); text-align:left; }
    .pi-table thead th {
        position:sticky; top:0; z-index:2; background:var(--surface-2);
        font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.02em;
        color:var(--text-muted); border-bottom:2px solid var(--border);
    }
    .pi-table tbody tr:hover { background:var(--surface-2); }
    .pi-num { text-align:right; font-variant-numeric:tabular-nums; }
    .pi-muted { color:var(--text-muted); }
    .pi-trunc { max-width:190px; overflow:hidden; text-overflow:ellipsis; }
    .pi-btn-xs { font-size:11px; padding:.2rem .5rem; }
    .pi-ref { font-weight:600; color:var(--ok); }
    .pi-col-uuid { max-width:150px; overflow:hidden; text-overflow:ellipsis; }
</style>

@if($invoices->isEmpty())
<div class="empty-state">
    <i class="fa-solid fa-users"></i>
    <h3>Sin recibos de nómina</h3>
    <p>No hay comprobantes de nómina en el periodo.</p>
</div>
@else
<div class="pi-wrap">
    <table class="pi-table">
        <thead>
            <tr>
                <th style="width:1%;">Acciones</th>
                <th>Estado SAT</th>
                <th>Fecha</th>
                <th>Folio</th>
                <th>UUID</th>
                <th>RFC Empleado</th>
                <th>Nombre Empleado</th>
                <th>Cuenta contable</th>
                <th class="pi-num">Subtotal</th>
                <th class="pi-num">IVA</th>
                <th class="pi-num">Total</th>
                <th>Forma Pago</th>
                <th>Método Pago</th>
                <th>Recibo Pago</th>
                <th>Fecha Pago</th>
                <th class="pi-num">Importe Pago</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $inv)
                @php $pago = $inv->pago_resumen; @endphp
                <tr data-invoice="{{ $inv->id }}">
                    <td style="white-space:nowrap;">
                        <a href="{{ route('invoices.show', $inv) }}" class="btn btn-soft pi-btn-xs" title="Ver recibo">
                            <i class="fa-solid fa-eye"></i>
                        </a>
                    </td>
                    <td>
                        <span class="badge-status {{ $inv->cancelado ? 's-danger' : 's-success' }}" style="font-size:10.5px;">
                            {{ $inv->estado_sat }}
                        </span>
                    </td>
                    <td class="data">{{ optional($inv->fecha_emision)->format('Y-m-d') }}</td>
                    <td class="data">{{ $inv->serie }}{{ $inv->folio }}</td>
                    <td class="data pi-col-uuid" title="{{ $inv->uuid }}">{{ $inv->uuid }}</td>
                    <td class="data">{{ $inv->receptor_rfc }}</td>
                    <td class="pi-trunc" title="{{ $inv->receptor_nombre }}">{{ $inv->receptor_nombre }}</td>
                    <td class="data">
                        @if($inv->cuentaContable)
                            {{ $inv->cuentaContable->numero_cuenta }}
                        @else
                            <span class="pi-muted">—</span>
                        @endif
                    </td>
                    <td class="pi-num">${{ number_format($inv->subtotal, 2) }}</td>
                    <td class="pi-num pi-muted">{{ (float) $inv->iva_trasladado > 0.005 ? '$' . number_format($inv->iva_trasladado, 2) : '—' }}</td>
                    <td class="pi-num">${{ number_format($inv->total, 2) }}</td>
                    <td class="data">{{ $inv->forma_pago ?: '—' }}</td>
                    <td class="data">{{ $inv->metodo_pago ?: '—' }}</td>
                    <td>
                        <div style="display:flex; flex-direction:column; gap:3px; align-items:flex-start;">
                        @forelse($inv->pago_comp_refs as $c)
                            @if($c['id'])
                            <a href="{{ route('invoices.show', $c['id']) }}" class="pi-ref" title="Ver complemento de pago" style="text-decoration:none;">
                                {{ $c['folio'] ?? 'CEP' }} — {{ $c['fecha'] ?? '—' }} <i class="fa-solid fa-up-right-from-square" style="font-size:9px;"></i>
                            </a>
                            @else
                            <span class="pi-ref">{{ $c['folio'] ?? 'CEP' }} — {{ $c['fecha'] ?? '—' }}</span>
                            @endif
                        @empty
                            <span class="pi-muted">—</span>
                        @endforelse
                        </div>
                    </td>
                    <td class="data">{{ $pago['fecha'] ?? '—' }}</td>
                    <td class="pi-num">{{ $pago['importe'] !== null ? '$' . number_format($pago['importe'], 2) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <label class="pi-muted" style="font-size:12.5px;">Mostrar</label>
        <select class="form-select form-select-sm" style="width:auto;" onchange="
            const u = new window.URL(window.location.href);
            u.searchParams.set('per_page', this.value);
            u.searchParams.delete('page');
            window.location = u.toString();
        ">
            @foreach([25, 50, 100, 200] as $opt)
                <option value="{{ $opt }}" @selected(($perPage ?? 50) === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
        <span class="pi-muted" style="font-size:12.5px;">por página</span>
    </div>
    <div>{{ $invoices->links() }}</div>
</div>
@endif
