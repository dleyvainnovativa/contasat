{{-- Block C: the parsed payment detail for a pago complement (tipo P).
     Included from the filtered invoices view when $isPago. Shows, per payment
     complement, which invoices it settled and for how much. --}}

@php
    // Group the payment_documents for the invoices on this page by payment invoice.
    $links = \App\Models\PaymentDocument::whereIn('payment_invoice_id', $invoices->pluck('id'))
        ->with('paidInvoice')
        ->get()
        ->groupBy('payment_invoice_id');
@endphp

@if($links->isNotEmpty())
    <div class="card-clean mt-3" data-reveal>
        <div class="card-clean__head"><strong>Documentos relacionados</strong>
            <span class="text-muted" style="font-size:12px;">Qué facturas liquida cada pago</span>
        </div>
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Pago (folio)</th>
                        <th>Fecha pago</th>
                        <th>Forma</th>
                        <th>UUID factura pagada</th>
                        <th>Parc.</th>
                        <th class="text-end">Saldo ant.</th>
                        <th class="text-end">Pagado</th>
                        <th class="text-end">Saldo insoluto</th>
                        <th class="text-center">Vinculada</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $inv)
                        @foreach($links->get($inv->id, []) as $link)
                            <tr>
                                <td class="data">{{ $inv->serie }}{{ $inv->folio }}</td>
                                <td class="data" style="white-space:nowrap;">{{ $link->fecha_pago?->format('d/m/Y') }}</td>
                                <td class="data text-muted">{{ $link->forma_pago }}</td>
                                <td class="data text-muted" style="font-size:11px;" title="{{ $link->iddocumento }}">
                                    {{ \Illuminate\Support\Str::limit($link->iddocumento, 18, '…') }}
                                </td>
                                <td class="data text-center">{{ $link->num_parcialidad ?? '—' }}</td>
                                <td class="text-end data">{{ $link->imp_saldo_ant !== null ? number_format($link->imp_saldo_ant, 2) : '—' }}</td>
                                <td class="text-end data" style="font-weight:600;">{{ number_format($link->imp_pagado, 2) }}</td>
                                <td class="text-end data">{{ $link->imp_saldo_insoluto !== null ? number_format($link->imp_saldo_insoluto, 2) : '—' }}</td>
                                <td class="text-center">
                                    @if($link->isResolved())
                                        <span class="badge-status s-success" style="font-size:11px;" title="{{ $link->paidInvoice?->serie }}{{ $link->paidInvoice?->folio }}">
                                            <i class="fa-solid fa-link"></i> Sí
                                        </span>
                                    @else
                                        <span class="badge-status s-secondary" style="font-size:11px;" title="La factura pagada no está en el sistema">
                                            <i class="fa-solid fa-link-slash"></i> No
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
