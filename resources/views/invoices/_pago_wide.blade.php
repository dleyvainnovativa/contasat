{{-- Wide pago table (tipo P) — one row per complemento de pago. The comprobante
     itself carries no amounts; the figures come from its DoctoRelacionado links
     (issuedPayments), aggregated by pago_emitido_resumen. The per-document
     breakdown stays in _pago_detail below. Direction (emitido/recibido) picks the
     contraparte. Expects $invoices / $perPage, and $view or $filtro for direction. --}}
@php $mod = $view ?? ($filtro ?? ''); $isEmitido = $mod === 'pago_emitido'; @endphp
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
    .pi-col-uuid { max-width:150px; overflow:hidden; text-overflow:ellipsis; }
    .pi-chip { font-weight:600; color:var(--brand-600); }
    [data-theme="dark"] .pi-chip { color:var(--brand-300, var(--brand)); }
</style>

@if($invoices->isEmpty())
<div class="empty-state">
    <i class="fa-solid fa-money-bill-transfer"></i>
    <h3>Sin complementos de pago</h3>
    <p>No hay comprobantes de pago {{ $isEmitido ? 'emitidos' : 'recibidos' }} en el periodo.</p>
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
                <th>{{ $isEmitido ? 'RFC Receptor' : 'RFC Emisor' }}</th>
                <th>{{ $isEmitido ? 'Nombre Receptor' : 'Nombre Emisor' }}</th>
                <th>Forma Pago</th>
                <th>Moneda</th>
                <th class="pi-num">Monto del pago</th>
                <th>Núm. Operación</th>
                <th class="pi-num">Docs liquidados</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $inv)
                @php
                    $r   = $inv->pago_emitido_resumen;
                    $rfc = $isEmitido ? $inv->receptor_rfc : $inv->emisor_rfc;
                    $nom = $isEmitido ? $inv->receptor_nombre : $inv->emisor_nombre;
                @endphp
                <tr data-invoice="{{ $inv->id }}">
                    <td style="white-space:nowrap;">
                        <a href="{{ route('invoices.show', $inv) }}" class="btn btn-soft pi-btn-xs" title="Ver complemento">
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
                    <td class="data">{{ $rfc }}</td>
                    <td class="pi-trunc" title="{{ $nom }}">{{ $nom ?: '—' }}</td>
                    <td class="data">{{ $r['forma'] ?: '—' }}</td>
                    <td class="data">{{ $r['moneda'] ?: '—' }}</td>
                    <td class="pi-num">{{ $r['monto'] > 0 ? '$' . number_format($r['monto'], 2) : '—' }}</td>
                    <td class="data pi-muted">{{ $r['num_operacion'] ?: '—' }}</td>
                    <td class="pi-num">
                        @if($r['docs'] > 0)
                            <span class="pi-chip">{{ $r['docs'] }}</span>
                            @if($r['resueltos'] < $r['docs'])
                                <span class="pi-muted" title="Documentos liquidados cuya factura no está en el sistema">({{ $r['resueltos'] }} vinc.)</span>
                            @endif
                        @else
                            <span class="pi-muted">—</span>
                        @endif
                    </td>
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
