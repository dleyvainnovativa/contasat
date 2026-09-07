@extends('layouts.app')
@section('title', 'Provisión de Ingresos · ContaSAT')

@section('content')
<style>
    /* Wide working table: horizontal scroll, sticky header, compact cells. */
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
    .pi-group-16 { background:color-mix(in srgb, var(--brand-050) 40%, transparent); }
    [data-theme="dark"] .pi-group-16 { background:color-mix(in srgb, var(--surface-2) 60%, transparent); }
    .pi-btn-xs { font-size:11px; padding:.2rem .5rem; }
    .pi-ref { font-weight:600; color:var(--ok); }
    .pi-col-uuid { max-width:150px; overflow:hidden; text-overflow:ellipsis; }
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
                <th>Provisión</th>
                <th>Póliza Ingreso</th>
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
                    <td class="pi-num pi-muted">—</td>
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

                    {{-- Action: Generar Provisión → shows Dr ref once done --}}
                    <td>
                        @if($inv->provision_ref)
                            <span class="pi-ref" data-prov-ref>{{ $inv->provision_ref }}</span>
                        @else
                            <button class="btn btn-brand pi-btn-xs" data-gen-provision>
                                <i class="fa-solid fa-file-circle-plus"></i> Provisión
                            </button>
                        @endif
                    </td>

                    {{-- Action: Generar Póliza Ingreso (cobro) → shows Ig ref once done --}}
                    <td>
                        @if($inv->ingreso_ref)
                            <span class="pi-ref" data-ing-ref>{{ $inv->ingreso_ref }}</span>
                        @else
                            <button class="btn btn-soft pi-btn-xs" data-gen-ingreso
                                {{ $inv->provision_ref ? '' : 'disabled title=Genera-primero-la-provisión' }}>
                                <i class="fa-solid fa-file-circle-plus"></i> Ingreso
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="26" class="pi-muted" style="text-align:center; padding:2rem;">
                    No hay facturas de ingreso en este periodo.
                </td></tr>
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
                <option value="{{ $opt }}" @selected(($perPage ?? 50) === $opt)>{{ $opt }}</option>
            @endforeach
        </select>
        <span class="pi-muted" style="font-size:12.5px;">por página</span>
    </div>
    <div>{{ $invoices->links() }}</div>
</div>
@endsection

@push('scripts')
<script type="module">
    (function () {
        const base = @json(url('invoices'));

        // Generate the póliza de provisión inline; on success swap the button for its ref.
        document.querySelectorAll('[data-gen-provision]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const row = btn.closest('tr');
                const id = row.dataset.invoice;
                await App.loading.button(btn, async () => {
                    try {
                        // No per-concepto account picker here — the service falls back to
                        // each invoice's own abono account. (The classify modal remains the
                        // place to choose accounts per concepto.)
                        const res = await App.http.post(`${base}/${id}/provision`, { concept_accounts: {} });
                        App.toast.success(res.message);
                        if (res.poliza?.num_iden) {
                            btn.outerHTML = `<span class="pi-ref" data-prov-ref>${res.poliza.num_iden}</span>`;
                            // Enable the ingreso button now that provisión exists.
                            const ingBtn = row.querySelector('[data-gen-ingreso]');
                            if (ingBtn) { ingBtn.disabled = false; ingBtn.removeAttribute('title'); }
                        } else {
                            setTimeout(() => window.location.reload(), 800);
                        }
                    } catch (e) { App.toast.error(e.message); }
                });
            });
        });

        // Generate the póliza de ingreso/cobro inline (manual date = today).
        document.querySelectorAll('[data-gen-ingreso]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const row = btn.closest('tr');
                const id = row.dataset.invoice;
                await App.loading.button(btn, async () => {
                    try {
                        const res = await App.http.post(`${base}/${id}/cobro`, {
                            origen: 'manual',
                            fecha_pago: new Date().toISOString().slice(0, 10),
                        });
                        App.toast.success(res.message);
                        if (res.poliza?.num_iden) {
                            btn.outerHTML = `<span class="pi-ref" data-ing-ref>${res.poliza.num_iden}</span>`;
                        } else {
                            setTimeout(() => window.location.reload(), 800);
                        }
                    } catch (e) { App.toast.error(e.message); }
                });
            });
        });
    })();
</script>
@endpush