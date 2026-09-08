@extends('layouts.app')
@section('title', 'Provisión de Egresos · ContaSAT')

@section('content')
<style>
    .pe-wrap {
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: auto;
        background: var(--surface);
        max-height: 72vh;
    }

    .pe-table {
        border-collapse: separate;
        border-spacing: 0;
        font-size: 12px;
        white-space: nowrap;
        min-width: 100%;
    }

    .pe-table th,
    .pe-table td {
        padding: .5rem .6rem;
        border-bottom: 1px solid var(--border);
        text-align: left;
    }

    .pe-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: var(--surface-2);
        font-weight: 600;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .02em;
        color: var(--text-muted);
        border-bottom: 2px solid var(--border);
    }

    .pe-table tbody tr:hover {
        background: var(--surface-2);
    }

    .pe-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .pe-muted {
        color: var(--text-muted);
    }

    .pe-trunc {
        max-width: 190px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pe-group-16 {
        background: color-mix(in srgb, var(--brand-050) 40%, transparent);
    }

    [data-theme="dark"] .pe-group-16 {
        background: color-mix(in srgb, var(--surface-2) 60%, transparent);
    }

    .pe-btn-xs {
        font-size: 11px;
        padding: .2rem .5rem;
    }

    .pe-ref {
        font-weight: 600;
        color: var(--ok);
    }

    .pe-col-uuid {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

<div class="page-head" data-reveal>
    <div>
        <h1>Provisión de Egresos</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2" data-reveal>
    <form method="GET" class="d-flex gap-2" style="max-width:360px;">
        <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm"
            placeholder="Buscar folio, UUID, proveedor…">
        <button class="btn btn-soft btn-sm">Buscar</button>
    </form>
    <div class="pe-muted" style="font-size:12px;">{{ $invoices->total() }} facturas de egreso</div>
</div>

<div class="pe-wrap" data-reveal>
    <table class="pe-table">
        <thead>
            <tr>
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
                <th>Provisión</th>
                <th>Pago</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $inv)
            @php
            $base = $inv->egreso_base_breakdown;
            $pago = $inv->pago_resumen;
            $fmt = fn($v) => $v === null ? '—' : '$' . number_format($v, 2);
            $pendiente = ! $pago['recibo'];
            @endphp
            <tr data-invoice="{{ $inv->id }}">
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

                {{-- Generar Provisión de gasto --}}
                <td>
                    @if($inv->provision_gasto_ref)
                    <span class="pe-ref">{{ $inv->provision_gasto_ref }}</span>
                    @else
                    <button class="btn btn-brand pe-btn-xs" data-gen-provision>
                        <i class="fa-solid fa-file-circle-plus"></i> Provisión
                    </button>
                    @endif
                </td>

                {{-- Generar Pago de gasto --}}
                <td>
                    @if($inv->pago_gasto_ref)
                    <span class="pe-ref">{{ $inv->pago_gasto_ref }}</span>
                    @else
                    <button class="btn btn-soft pe-btn-xs" data-gen-pago
                        {{ $inv->provision_gasto_ref ? '' : 'disabled title=Genera-primero-la-provisión' }}>
                        <i class="fa-solid fa-file-circle-plus"></i> Pago
                    </button>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="27" class="pe-muted" style="text-align:center; padding:2rem;">
                    No hay facturas de egreso en este periodo.
                </td>
            </tr>
            @endforelse
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
            <option value="{{ $opt }}" @selected(($perPage ?? 50)===$opt)>{{ $opt }}</option>
            @endforeach
        </select>
        <span class="pe-muted" style="font-size:12.5px;">por página</span>
    </div>
    <div>{{ $invoices->links() }}</div>
</div>
@endsection

@push('scripts')
<script type="module">
    (function() {
        const base = @json(url('provision-egresos'));

        document.querySelectorAll('[data-gen-provision]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const row = btn.closest('tr');
                const id = row.dataset.invoice;
                await App.loading.button(btn, async () => {
                    try {
                        const res = await App.http.post(`${base}/${id}/provision`, {});
                        App.toast.success(res.message);
                        if (res.poliza?.num_iden) {
                            btn.outerHTML = `<span class="pe-ref">${res.poliza.num_iden}</span>`;
                            const pagoBtn = row.querySelector('[data-gen-pago]');
                            if (pagoBtn) {
                                pagoBtn.disabled = false;
                                pagoBtn.removeAttribute('title');
                            }
                        } else {
                            setTimeout(() => window.location.reload(), 800);
                        }
                    } catch (e) {
                        App.toast.error(e.message);
                    }
                });
            });
        });

        document.querySelectorAll('[data-gen-pago]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const row = btn.closest('tr');
                const id = row.dataset.invoice;
                await App.loading.button(btn, async () => {
                    try {
                        const res = await App.http.post(`${base}/${id}/pago`, {
                            fecha_pago: new Date().toISOString().slice(0, 10),
                        });
                        App.toast.success(res.message);
                        if (res.poliza?.num_iden) {
                            btn.outerHTML = `<span class="pe-ref">${res.poliza.num_iden}</span>`;
                        } else {
                            setTimeout(() => window.location.reload(), 800);
                        }
                    } catch (e) {
                        App.toast.error(e.message);
                    }
                });
            });
        });
    })();
</script>
@endpush