{{-- Wide expense detail table (formerly the provision-egresos page), now the
     gasto tab body. Acciones: show invoice, Provisión de gasto (opens classify
     modal, which generates provisión de gasto on confirm), Pago de gasto (opens
     the pago modal). Expects the same $invoices / $q / $perPage the filtered
     view provides. --}}
<style>
    .pe-wrap { border:1px solid var(--border); border-radius:var(--radius-lg); overflow:auto; background:var(--surface); max-height:72vh; }
    .pe-table { border-collapse:separate; border-spacing:0; font-size:12px; white-space:nowrap; min-width:100%; }
    .pe-table th, .pe-table td { padding:.5rem .6rem; border-bottom:1px solid var(--border); text-align:left; }
    .pe-table thead th {
        position:sticky; top:0; z-index:2; background:var(--surface-2);
        font-weight:600; font-size:11px; text-transform:uppercase; letter-spacing:.02em;
        color:var(--text-muted); border-bottom:2px solid var(--border);
    }
    .pe-table tbody tr:hover { background:var(--surface-2); }
    .pe-num { text-align:right; font-variant-numeric:tabular-nums; }
    .pe-muted { color:var(--text-muted); }
    .pe-trunc { max-width:190px; overflow:hidden; text-overflow:ellipsis; }
    .pe-group-16 { background:color-mix(in srgb, var(--brand-050) 40%, transparent); }
    [data-theme="dark"] .pe-group-16 { background:color-mix(in srgb, var(--surface-2) 60%, transparent); }
    .pe-btn-xs { font-size:11px; padding:.2rem .5rem; }
    .pe-ref { font-weight:600; color:var(--ok); }
    .pe-col-uuid { max-width:150px; overflow:hidden; text-overflow:ellipsis; }
</style>

@if($invoices->isEmpty())
<div class="empty-state">
    <i class="fa-solid fa-file-invoice"></i>
    <h3>Sin facturas</h3>
    <p>No hay comprobantes de egreso en el periodo.</p>
</div>
@else
<div class="pe-wrap">
    <table class="pe-table">
        <thead>
            <tr>
                <th style="width:1%;">Acciones</th>
                <th>Documento</th>
                <th>Estado SAT</th>
                <th>Fecha</th>
                <th>Folio</th>
                <th>UUID</th>
                <th>RFC Proveedor</th>
                <th>Nombre Proveedor</th>
                <th class="pe-num pe-group-16">Egr. 16%</th>
                <th class="pe-num">Egr. 0%</th>
                <th class="pe-num">Egr. Exento</th>
                <th class="pe-num">Egr. No Objeto</th>
                <th class="pe-num">Parte No Deducible</th>
                <th class="pe-num">Subtotal</th>
                <th class="pe-num">IVA 16%</th>
                <th class="pe-num">Otros Imp.</th>
                <th class="pe-num">IVA por Retener</th>
                <th class="pe-num">ISR por Retener</th>
                <th class="pe-num">Total</th>
                <th>Concepto XML</th>
                <th>Recibo Pago</th>
                <th>Forma Pago</th>
                <th>Método Pago</th>
                <th>Fecha Pago</th>
                <th class="pe-num">Importe Pago</th>
                <th>Pendiente</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $inv)
                @php
                    $base = $inv->egreso_base_breakdown;
                    $pago = $inv->pago_resumen;
                    $fmt = fn($v) => $v === null ? '—' : '$' . number_format($v, 2);
                    $pendiente = ! $pago['recibo'];
                @endphp
                <tr data-invoice="{{ $inv->id }}">
                    <td style="white-space:nowrap;">
                        <div class="d-flex align-items-center gap-1">
                            <a href="{{ route('invoices.show', $inv) }}" class="btn btn-soft pe-btn-xs" title="Ver factura">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                            {{-- Provisión de gasto: ref if generated, else classify modal --}}
                            @if($inv->provision_gasto_ref)
                                <span class="pe-ref" title="Provisión de gasto generada">{{ $inv->provision_gasto_ref }}</span>
                            @else
                                <button class="btn btn-brand pe-btn-xs" data-classify="{{ $inv->id }}" title="Clasificar y generar provisión de gasto">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                            @endif
                            {{-- Pago de gasto: ref if generated, else pago modal --}}
                            @if($inv->pago_gasto_ref)
                                <span class="pe-ref" title="Pago de gasto generado">{{ $inv->pago_gasto_ref }}</span>
                            @else
                                <button class="btn btn-soft pe-btn-xs" data-pago="{{ $inv->id }}"
                                    data-folio="{{ $inv->serie }}{{ $inv->folio }}"
                                    data-total="{{ $inv->total }}"
                                    {{ $inv->provision_gasto_ref ? '' : 'disabled title=Genera-primero-la-provisión' }}>
                                    <i class="fa-solid fa-money-bill-wave"></i>
                                </button>
                            @endif
                        </div>
                    </td>
                    <td>Factura</td>
                    <td>
                        <span class="badge-status {{ $inv->cancelado ? 's-danger' : 's-success' }}" style="font-size:10.5px;">
                            {{ $inv->estado_sat }}
                        </span>
                    </td>
                    <td class="data">{{ optional($inv->fecha_emision)->format('Y-m-d') }}</td>
                    <td class="data">{{ $inv->serie }}{{ $inv->folio }}</td>
                    <td class="data pe-col-uuid" title="{{ $inv->uuid }}">{{ $inv->uuid }}</td>
                    <td class="data">{{ $inv->emisor_rfc }}</td>
                    <td class="pe-trunc" title="{{ $inv->emisor_nombre }}">{{ $inv->emisor_nombre }}</td>
                    <td class="pe-num pe-group-16">{{ $fmt($base['16']) }}</td>
                    <td class="pe-num">{{ $fmt($base['0']) }}</td>
                    <td class="pe-num">{{ $fmt($base['exento']) }}</td>
                    <td class="pe-num">{{ $fmt($base['no_objeto']) }}</td>
                    <td class="pe-num">{{ $base['no_deducible'] > 0 ? '$' . number_format($base['no_deducible'], 2) : '—' }}</td>
                    <td class="pe-num">${{ number_format($inv->subtotal_egreso, 2) }}</td>
                    <td class="pe-num">${{ number_format($inv->iva_trasladado, 2) }}</td>
                    <td class="pe-num pe-muted">{{ abs($inv->otros_impuestos) > 0.005 ? '$' . number_format($inv->otros_impuestos, 2) : '—' }}</td>
                    <td class="pe-num">{{ $inv->iva_retenido > 0 ? '$' . number_format($inv->iva_retenido, 2) : '—' }}</td>
                    <td class="pe-num">{{ $inv->isr_retenido > 0 ? '$' . number_format($inv->isr_retenido, 2) : '—' }}</td>
                    <td class="pe-num">${{ number_format($inv->total, 2) }}</td>
                    <td class="pe-trunc" title="{{ $inv->conceptos_resumen }}">{{ \Illuminate\Support\Str::limit($inv->conceptos_resumen, 40) ?: '—' }}</td>
                    <td>
                        @if($pago['recibo'])
                            <span class="pe-ref"><i class="fa-solid fa-check"></i> CEP</span>
                        @else
                            <span class="pe-muted">—</span>
                        @endif
                    </td>
                    <td class="data">{{ $inv->forma_pago ?: '—' }}</td>
                    <td class="data">{{ $inv->metodo_pago ?: '—' }}</td>
                    <td class="data">{{ $pago['fecha'] ?? '—' }}</td>
                    <td class="pe-num">{{ $pago['importe'] !== null ? '$' . number_format($pago['importe'], 2) : '—' }}</td>
                    <td>
                        @if($pendiente)
                            <span class="badge-status s-warning" style="font-size:10.5px;">No pagado</span>
                        @else
                            <span class="badge-status s-success" style="font-size:10.5px;">Pagado</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <label class="pe-muted" style="font-size:12.5px;">Mostrar</label>
        <select class="form-select form-select-sm" style="width:auto;" onchange="
            const u = new URL(window.location);
            u.searchParams.set('per_page', this.value);
            u.searchParams.delete('page');
            window.location = u.toString();
        ">
            @foreach([25, 50, 100, 200] as $opt)
                <option value="{{ $opt }}" @selected(($perPage ?? 50) === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
        <span class="pe-muted" style="font-size:12.5px;">por página</span>
    </div>
    <div>{{ $invoices->links() }}</div>
</div>
@endif

{{-- Classify modal (Provisión buttons) --}}
@include('invoices._classify_modal')

{{-- Pago de gasto modal --}}
<div class="modal fade" id="pago-gasto-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg); border-color:var(--border); background:var(--surface);">
            <div class="modal-body p-4">
                <h5 class="mb-1" style="font-weight:600;">Pago de gasto</h5>
                <p class="text-muted mb-3" style="font-size:13px;">
                    <span id="pg-folio" class="data"></span> · <span id="pg-total"></span>
                </p>
                <div class="mb-3">
                    <label class="form-label">Fecha de pago</label>
                    <input type="date" id="pg-fecha" class="form-control">
                    <div class="form-hint" style="font-size:11.5px;">
                        Genera la póliza de pago: Debe 201.01.# proveedor / Haber 102.01 bancos,
                        más la reclasificación de IVA 119.01 → 118.01.
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand btn-icon" id="pg-submit">
                        <i class="fa-solid fa-check"></i> Generar pago
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script type="module">
    (function () {
        const base = @json(url('provision-egresos'));
        let pagoId = null;

        document.querySelectorAll('[data-pago]').forEach(btn => {
            btn.addEventListener('click', () => {
                pagoId = btn.dataset.pago;
                document.getElementById('pg-folio').textContent = btn.dataset.folio || '—';
                document.getElementById('pg-total').textContent =
                    '$' + Number(btn.dataset.total || 0).toLocaleString('es-MX', { minimumFractionDigits: 2 });
                document.getElementById('pg-fecha').value = new Date().toISOString().slice(0, 10);
                App.modal.show('pago-gasto-modal');
            });
        });

        const submit = document.getElementById('pg-submit');
        submit?.addEventListener('click', async () => {
            const fecha = document.getElementById('pg-fecha').value;
            if (!fecha) { App.toast.warning('Ingresa la fecha de pago.'); return; }
            await App.loading.button(submit, async () => {
                try {
                    const res = await App.http.post(`${base}/${pagoId}/pago`, { fecha_pago: fecha });
                    App.toast.success(res.message);
                    App.modal.hide('pago-gasto-modal');
                    setTimeout(() => window.location.reload(), 900);
                } catch (e) { App.toast.error(e.message); }
            });
        });
    })();
</script>
@endpush
