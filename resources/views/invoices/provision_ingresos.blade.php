@extends('layouts.app')
@section('title', 'Provisión de Ingresos · ContaSAT')

@section('content')
<style>
    /* Wide working table: horizontal scroll, sticky header, compact cells. */
    .pi-wrap {
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: auto;
        background: var(--surface);
        max-height: 72vh;
    }

    .pi-table {
        border-collapse: separate;
        border-spacing: 0;
        font-size: 12px;
        white-space: nowrap;
        min-width: 100%;
    }

    .pi-table th,
    .pi-table td {
        padding: .5rem .6rem;
        border-bottom: 1px solid var(--border);
        text-align: left;
    }

    .pi-table thead th {
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

    .pi-table tbody tr:hover {
        background: var(--surface-2);
    }

    .pi-num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .pi-muted {
        color: var(--text-muted);
    }

    .pi-trunc {
        max-width: 190px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pi-group-16 {
        background: color-mix(in srgb, var(--brand-050) 40%, transparent);
    }

    [data-theme="dark"] .pi-group-16 {
        background: color-mix(in srgb, var(--surface-2) 60%, transparent);
    }

    .pi-btn-xs {
        font-size: 11px;
        padding: .2rem .5rem;
    }

    .pi-ref {
        font-weight: 600;
        color: var(--ok);
    }

    .pi-col-uuid {
        max-width: 150px;
        overflow: hidden;
        text-overflow: ellipsis;
    }
</style>

<div class="page-head" data-reveal>
    <div>
        <h1>Provisión de Ingresos</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2" data-reveal>
    <form method="GET" class="d-flex gap-2" style="max-width:360px;">
        <input type="text" name="q" value="{{ $q }}" class="form-control form-control-sm"
            placeholder="Buscar folio, UUID, receptor…">
        <button class="btn btn-soft btn-sm">Buscar</button>
    </form>
    <div class="pi-muted" style="font-size:12px;">{{ $invoices->total() }} facturas de ingreso</div>
</div>

<div class="pi-wrap" data-reveal>
    <table class="pi-table">
        <thead>
            <tr>
                <th style="width:1%;">Acciones</th>
                <th>Documento</th>
                <th>Estado SAT</th>
                <th>Fecha</th>
                <th>Folio</th>
                <th>UUID</th>
                <th>RFC Receptor</th>
                <th>Nombre Receptor</th>
                <th class="pi-num pi-group-16">Ing. Base 16%</th>
                <th class="pi-num">Ing. Base 0%</th>
                <th class="pi-num">Ing. Exento</th>
                <th class="pi-num">Ing. No Objeto</th>
                <th class="pi-num">Subtotal</th>
                <th class="pi-num">IVA</th>
                <th class="pi-num">Otros Imp.</th>
                <th class="pi-num">IVA Ret.</th>
                <th class="pi-num">ISR Ret.</th>
                <th class="pi-num">Total</th>
                <th>Concepto XML</th>
                <th>Forma Pago</th>
                <th>Método Pago</th>
                <th>Recibo Pago</th>
                <th>Fecha Pago</th>
                <th class="pi-num">Importe Pago</th>
                <th>Cta Pago</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoices as $inv)
            @php
            $base = $inv->iva_base_breakdown;
            $pago = $inv->pago_resumen;
            $fmt = fn($v) => $v === null ? '—' : '$' . number_format($v, 2);
            @endphp
            <tr data-invoice="{{ $inv->id }}">
                <td style="white-space:nowrap;">
                    <div class="d-flex align-items-center gap-1">
                        {{-- Provisión: shows Dr ref if generated, else opens the classify modal --}}
                        @if($inv->provision_ref)
                        <span class="pi-ref" title="Provisión generada">{{ $inv->provision_ref }}</span>
                        @else
                        <button class="btn btn-brand pi-btn-xs" data-classify="{{ $inv->id }}" title="Clasificar y generar provisión">
                            <i class="fa-solid fa-pen"></i> Provisión
                        </button>
                        @endif

                        {{-- Cobro: shows Ig ref if generated, else opens the Asiento de cobro modal --}}
                        @if($inv->ingreso_ref)
                        <span class="pi-ref" title="Cobro generado">{{ $inv->ingreso_ref }}</span>
                        @else
                        <button class="btn btn-soft pi-btn-xs" data-cobro="{{ $inv->id }}"
                            data-folio="{{ $inv->serie }}{{ $inv->folio }}"
                            data-total="{{ $inv->total }}"
                            {{ $inv->provision_ref ? '' : 'disabled title=Genera-primero-la-provisión' }}>
                            <i class="fa-solid fa-money-bill-wave"></i> Cobro
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
                <td class="data pi-col-uuid" title="{{ $inv->uuid }}">{{ $inv->uuid }}</td>
                <td class="data">{{ $inv->receptor_rfc }}</td>
                <td class="pi-trunc" title="{{ $inv->receptor_nombre }}">{{ $inv->receptor_nombre }}</td>
                <td class="pi-num pi-group-16">{{ $fmt($base['16']) }}</td>
                <td class="pi-num">{{ $fmt($base['0']) }}</td>
                <td class="pi-num">{{ $fmt($base['exento']) }}</td>
                <td class="pi-num">{{ $fmt($base['no_objeto']) }}</td>
                <td class="pi-num">${{ number_format($inv->subtotal, 2) }}</td>
                <td class="pi-num">${{ number_format($inv->iva_trasladado, 2) }}</td>
                <td class="pi-num pi-muted">{{ abs($inv->otros_impuestos) > 0.005 ? '$' . number_format($inv->otros_impuestos, 2) : '—' }}</td>
                <td class="pi-num">${{ number_format($inv->iva_retenido, 2) }}</td>
                <td class="pi-num">${{ number_format($inv->isr_retenido, 2) }}</td>
                <td class="pi-num">${{ number_format($inv->total, 2) }}</td>
                <td class="pi-trunc" title="{{ $inv->conceptos_resumen }}">{{ \Illuminate\Support\Str::limit($inv->conceptos_resumen, 40) ?: '—' }}</td>
                <td class="data">{{ $inv->forma_pago ?: '—' }}</td>
                <td class="data">{{ $inv->metodo_pago ?: '—' }}</td>
                <td>
                    @if($pago['recibo'])
                    <span class="pi-ref"><i class="fa-solid fa-check"></i> CEP</span>
                    @else
                    <span class="pi-muted">—</span>
                    @endif
                </td>
                <td class="data">{{ $pago['fecha'] ?? '—' }}</td>
                <td class="pi-num">{{ $pago['importe'] !== null ? '$' . number_format($pago['importe'], 2) : '—' }}</td>
                <td class="data pi-muted">{{ $pago['recibo'] ? '102.01' : '—' }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="25" class="pi-muted" style="text-align:center; padding:2rem;">
                    No hay facturas de ingreso en este periodo.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <label class="pi-muted" style="font-size:12.5px;">Mostrar</label>
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
        <span class="pi-muted" style="font-size:12.5px;">por página</span>
    </div>
    <div>{{ $invoices->links() }}</div>
</div>

{{-- Classify modal: the "Provisión" buttons (data-classify) open this; on confirm
     it classifies and generates the provisión, then reloads. --}}
@include('invoices._classify_modal')

{{-- Asiento de cobro modal: minimal date + generate, hitting the cobro endpoint. --}}
<div class="modal fade" id="cobro-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg); border-color:var(--border); background:var(--surface);">
            <div class="modal-body p-4">
                <h5 class="mb-1" style="font-weight:600;">Asiento de cobro</h5>
                <p class="text-muted mb-3" style="font-size:13px;">
                    <span id="cb-folio" class="data"></span> · <span id="cb-total"></span>
                </p>

                <div class="mb-3">
                    <label class="form-label">Fecha de pago</label>
                    <input type="date" id="cb-fecha" class="form-control">
                    <div class="form-hint" style="font-size:11.5px;">
                        Genera la póliza de cobro: Debe 102.01 bancos / Haber 105.01.# cliente,
                        más la reclasificación de IVA 209.01 → 208.01.
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand btn-icon" id="cb-submit">
                        <i class="fa-solid fa-check"></i> Generar cobro
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="module">
    (function() {
        const base = @json(url('invoices'));
        let cobroId = null;

        // Open the Asiento de cobro modal from a row's Cobro button.
        document.querySelectorAll('[data-cobro]').forEach(btn => {
            btn.addEventListener('click', () => {
                cobroId = btn.dataset.cobro;
                document.getElementById('cb-folio').textContent = btn.dataset.folio || '—';
                document.getElementById('cb-total').textContent =
                    '$' + Number(btn.dataset.total || 0).toLocaleString('es-MX', {
                        minimumFractionDigits: 2
                    });
                document.getElementById('cb-fecha').value = new Date().toISOString().slice(0, 10);
                App.modal.show('cobro-modal');
            });
        });

        const submit = document.getElementById('cb-submit');
        submit?.addEventListener('click', async () => {
            const fecha = document.getElementById('cb-fecha').value;
            if (!fecha) {
                App.toast.warning('Ingresa la fecha de pago.');
                return;
            }
            await App.loading.button(submit, async () => {
                try {
                    const res = await App.http.post(`${base}/${cobroId}/cobro`, {
                        origen: 'manual',
                        fecha_pago: fecha,
                    });
                    App.toast.success(res.message);
                    App.modal.hide('cobro-modal');
                    setTimeout(() => window.location.reload(), 900);
                } catch (e) {
                    App.toast.error(e.message);
                }
            });
        });
    })();
</script>
@endpush