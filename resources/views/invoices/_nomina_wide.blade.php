{{-- Wide nómina table (tipo N) — full CFDI Nómina 1.2 layout from nomina_data.
     Core identity + totals always show; percepción (P) / deducción (D) / otro
     pago (OP) columns show only where there's a non-zero value in the current
     set, with a button to reveal every column. Expects $invoices / $perPage and
     the nomina-shaped $footerTotals. --}}
@php
    $PERC = \App\Support\NominaColumns::percepciones();
    $DED  = \App\Support\NominaColumns::deducciones();
    $OP   = \App\Support\NominaColumns::otrosPagos();

    // Which P/D/OP codes carry a non-zero value somewhere on this page.
    $actP = $actD = $actO = [];
    foreach ($invoices as $i) {
        $nd = $i->nomina_data ?? [];
        foreach (($nd['percepciones'] ?? []) as $c => $v) {
            if ((float) ($v['exe'] ?? 0) != 0 || (float) ($v['gra'] ?? 0) != 0) $actP[$c] = true;
        }
        foreach (($nd['deducciones'] ?? []) as $c => $v) { if ((float) $v != 0) $actD[$c] = true; }
        foreach (($nd['otros_pagos'] ?? []) as $c => $v) { if ((float) $v != 0) $actO[$c] = true; }
    }
    $money = fn ($v) => (float) $v != 0 ? '$' . number_format((float) $v, 2) : '';
@endphp
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
    .pi-table tfoot td { position:sticky; bottom:0; z-index:2; background:var(--surface-2); border-top:2px solid var(--border); font-weight:650; }
    .pi-muted { color:var(--text-muted); }
    .pi-trunc { max-width:170px; overflow:hidden; text-overflow:ellipsis; }
    .pi-btn-xs { font-size:11px; padding:.2rem .5rem; }
    .pi-ref { font-weight:600; color:var(--ok); }
    .pi-col-uuid { max-width:140px; overflow:hidden; text-overflow:ellipsis; }
    .nom-perc th, .nom-perc td { background:color-mix(in srgb, var(--ok) 6%, transparent); }
    .nom-ded th,  .nom-ded td  { background:color-mix(in srgb, var(--danger) 6%, transparent); }
    .nom-extra { display:none; }
    .nom-show-all .nom-extra { display:table-cell; }
</style>

@if($invoices->isEmpty())
<div class="empty-state">
    <i class="fa-solid fa-users"></i>
    <h3>Sin recibos de nómina</h3>
    <p>No hay comprobantes de nómina en el periodo.</p>
</div>
@else
<div class="nom-block">
    <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-soft pi-btn-xs"
            onclick="const b=this.closest('.nom-block');b.classList.toggle('nom-show-all');
                     this.innerHTML=b.classList.contains('nom-show-all')
                        ? '<i class=\'fa-solid fa-compress\'></i> Ocultar columnas en cero'
                        : '<i class=\'fa-solid fa-table-columns\'></i> Mostrar todas las columnas';">
            <i class="fa-solid fa-table-columns"></i> Mostrar todas las columnas
        </button>
    </div>
    <div class="pi-wrap">
        <table class="pi-table">
            <thead>
                <tr>
                    <th>Acciones</th>
                    <th>Estado SAT</th>
                    <th>Fecha</th>
                    <th>Folio</th>
                    <th>UUID</th>
                    <th>RFC Empleado</th>
                    <th>Nombre Empleado</th>
                    <th>Departamento</th>
                    <th>Puesto</th>
                    <th>NSS</th>
                    <th>CURP</th>
                    <th>Tipo Contrato</th>
                    <th>Tipo Régimen</th>
                    <th>Reg. Patronal</th>
                    <th>Periodicidad</th>
                    <th>Fecha Pago</th>
                    <th>F. Inicial</th>
                    <th>F. Final</th>
                    <th class="pi-num">Días</th>
                    <th class="pi-num">SDI</th>
                    <th>Antigüedad</th>
                    <th>Cuenta contable</th>
                    <th class="pi-num">Total Percep.</th>
                    <th class="pi-num">Total Deducc.</th>
                    <th class="pi-num">Total Otros</th>
                    <th class="pi-num">Total</th>
                    <th>Recibo Pago</th>
                    <th class="pi-num">Importe Pago</th>
                    @foreach($PERC as $code => $label)
                        <th class="pi-num nom-perc {{ isset($actP[$code]) ? '' : 'nom-extra' }}" title="Percepción {{ $code }} exento">P{{ $code }} {{ $label }} Exe</th>
                        <th class="pi-num nom-perc {{ isset($actP[$code]) ? '' : 'nom-extra' }}" title="Percepción {{ $code }} gravado">P{{ $code }} {{ $label }} Gra</th>
                    @endforeach
                    @foreach($DED as $code => $label)
                        <th class="pi-num nom-ded {{ isset($actD[$code]) ? '' : 'nom-extra' }}" title="Deducción {{ $code }}">D{{ $code }} {{ $label }}</th>
                    @endforeach
                    @foreach($OP as $code => $label)
                        <th class="pi-num {{ isset($actO[$code]) ? '' : 'nom-extra' }}" title="Otro pago {{ $code }}">OP{{ $code }} {{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($invoices as $inv)
                    @php
                        $pago = $inv->pago_resumen;
                        $nd   = $inv->nomina_data ?? [];
                        $h    = $nd['header'] ?? [];
                        $tot  = $nd['totales'] ?? [];
                        $np   = $nd['percepciones'] ?? [];
                        $ndd  = $nd['deducciones'] ?? [];
                        $nop  = $nd['otros_pagos'] ?? [];
                    @endphp
                    <tr data-invoice="{{ $inv->id }}">
                        <td style="white-space:nowrap;">
                            <a href="{{ route('invoices.show', $inv) }}" class="btn btn-soft pi-btn-xs" title="Ver recibo"><i class="fa-solid fa-eye"></i></a>
                        </td>
                        <td><span class="badge-status {{ $inv->cancelado ? 's-danger' : 's-success' }}" style="font-size:10.5px;">{{ $inv->estado_sat }}</span></td>
                        <td class="data">{{ optional($inv->fecha_emision)->format('Y-m-d') }}</td>
                        <td class="data">{{ $inv->serie }}{{ $inv->folio }}</td>
                        <td class="data pi-col-uuid" title="{{ $inv->uuid }}">{{ $inv->uuid }}</td>
                        <td class="data">{{ $inv->receptor_rfc }}</td>
                        <td class="pi-trunc" title="{{ $inv->receptor_nombre }}">{{ $inv->receptor_nombre }}</td>
                        <td class="pi-trunc">{{ $h['departamento'] ?? '—' }}</td>
                        <td class="pi-trunc">{{ $h['puesto'] ?? '—' }}</td>
                        <td class="data">{{ $h['nss'] ?? '—' }}</td>
                        <td class="data">{{ $h['curp'] ?? '—' }}</td>
                        <td class="data">{{ $h['tipo_contrato'] ?? '—' }}</td>
                        <td class="data">{{ $h['tipo_regimen'] ?? '—' }}</td>
                        <td class="data">{{ $h['registro_patronal'] ?? '—' }}</td>
                        <td class="data">{{ $h['periodicidad'] ?? '—' }}</td>
                        <td class="data">{{ $h['fecha_pago'] ?? '—' }}</td>
                        <td class="data">{{ $h['fecha_inicial_pago'] ?? '—' }}</td>
                        <td class="data">{{ $h['fecha_final_pago'] ?? '—' }}</td>
                        <td class="pi-num">{{ $h['num_dias_pagados'] ?? '—' }}</td>
                        <td class="pi-num">{{ isset($h['sdi']) ? number_format((float) $h['sdi'], 2) : '—' }}</td>
                        <td class="data">{{ $h['antiguedad'] ?? '—' }}</td>
                        <td class="data">{{ $inv->cuentaContable->numero_cuenta ?? '—' }}</td>
                        <td class="pi-num">${{ number_format((float) ($tot['total_percepciones'] ?? 0), 2) }}</td>
                        <td class="pi-num">${{ number_format((float) ($tot['total_deducciones'] ?? 0), 2) }}</td>
                        <td class="pi-num">${{ number_format((float) ($tot['total_otros_pagos'] ?? 0), 2) }}</td>
                        <td class="pi-num">${{ number_format((float) $inv->total, 2) }}</td>
                        <td>
                            <div style="display:flex; flex-direction:column; gap:3px; align-items:flex-start;">
                            @forelse($inv->pago_comp_refs as $c)
                                @if($c['id'])
                                <a href="{{ route('invoices.show', $c['id']) }}" class="pi-ref" title="Ver complemento de pago" style="text-decoration:none;">{{ $c['folio'] ?? 'CEP' }} — {{ $c['fecha'] ?? '—' }}</a>
                                @else
                                <span class="pi-ref">{{ $c['folio'] ?? 'CEP' }} — {{ $c['fecha'] ?? '—' }}</span>
                                @endif
                            @empty
                                <span class="pi-muted">—</span>
                            @endforelse
                            </div>
                        </td>
                        <td class="pi-num">{{ $pago['importe'] !== null ? '$' . number_format($pago['importe'], 2) : '—' }}</td>
                        @foreach($PERC as $code => $label)
                            <td class="pi-num nom-perc {{ isset($actP[$code]) ? '' : 'nom-extra' }}">{{ $money($np[$code]['exe'] ?? 0) }}</td>
                            <td class="pi-num nom-perc {{ isset($actP[$code]) ? '' : 'nom-extra' }}">{{ $money($np[$code]['gra'] ?? 0) }}</td>
                        @endforeach
                        @foreach($DED as $code => $label)
                            <td class="pi-num nom-ded {{ isset($actD[$code]) ? '' : 'nom-extra' }}">{{ $money($ndd[$code] ?? 0) }}</td>
                        @endforeach
                        @foreach($OP as $code => $label)
                            <td class="pi-num {{ isset($actO[$code]) ? '' : 'nom-extra' }}">{{ $money($nop[$code] ?? 0) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
            @isset($footerTotals)
            <tfoot>
                <tr>
                    <td>Totales · {{ (int) ($footerTotals['count'] ?? 0) }}</td>
                    <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                    <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                    <td class="pi-num">${{ number_format((float) ($footerTotals['total_percepciones'] ?? 0), 2) }}</td>
                    <td class="pi-num">${{ number_format((float) ($footerTotals['total_deducciones'] ?? 0), 2) }}</td>
                    <td class="pi-num">${{ number_format((float) ($footerTotals['total_otros_pagos'] ?? 0), 2) }}</td>
                    <td class="pi-num">${{ number_format((float) ($footerTotals['total'] ?? 0), 2) }}</td>
                    <td></td>
                    <td class="pi-num">${{ number_format((float) ($footerTotals['importe_pago'] ?? 0), 2) }}</td>
                    @foreach($PERC as $code => $label)
                        <td class="pi-num nom-perc {{ isset($actP[$code]) ? '' : 'nom-extra' }}">{{ $money($footerTotals['perc'][$code]['exe'] ?? 0) }}</td>
                        <td class="pi-num nom-perc {{ isset($actP[$code]) ? '' : 'nom-extra' }}">{{ $money($footerTotals['perc'][$code]['gra'] ?? 0) }}</td>
                    @endforeach
                    @foreach($DED as $code => $label)
                        <td class="pi-num nom-ded {{ isset($actD[$code]) ? '' : 'nom-extra' }}">{{ $money($footerTotals['ded'][$code] ?? 0) }}</td>
                    @endforeach
                    @foreach($OP as $code => $label)
                        <td class="pi-num {{ isset($actO[$code]) ? '' : 'nom-extra' }}">{{ $money($footerTotals['op'][$code] ?? 0) }}</td>
                    @endforeach
                </tr>
            </tfoot>
            @endisset
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
                @foreach([25, 100, 200, 500] as $opt)
                    <option value="{{ $opt }}" @selected(($perPage ?? 25) === $opt)>{{ $opt }}</option>
                @endforeach
            </select>
            <span class="pi-muted" style="font-size:12.5px;">por página</span>
        </div>
        <div>{{ $invoices->links() }}</div>
    </div>
</div>
@endif
