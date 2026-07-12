{{-- Invoices with no matching bank movement. These are typically unpaid, or paid
     in a different period — candidates for questions to the client. --}}
<div class="card-clean">
    <div class="card-clean__head">
        <strong>Facturas sin movimiento</strong>
        <span class="text-muted" style="font-size:13px;">CFDI sin pago identificado en el estado de cuenta</span>
    </div>
    @if($sinMovimiento->isEmpty())
        <div class="empty-state"><i class="fa-solid fa-check"></i><h3>Todo enlazado</h3><p>No hay facturas sin movimiento.</p></div>
    @else
        <div class="table-responsive">
            <table class="table-clean">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Contraparte</th><th>Folio</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                    @foreach($sinMovimiento as $inv)
                        <tr>
                            <td class="data" style="font-size:13px; white-space:nowrap;">{{ $inv->fecha_emision?->format('d/m/Y') }}</td>
                            <td>
                                @if($inv->tipo === 'emitida')
                                    <span class="badge-status s-success"><i class="fa-solid fa-arrow-up"></i> Emitida</span>
                                @else
                                    <span class="badge-status s-warning"><i class="fa-solid fa-arrow-down"></i> Recibida</span>
                                @endif
                            </td>
                            <td style="font-size:13px;">{{ $inv->tipo === 'emitida' ? ($inv->receptor_nombre ?: $inv->receptor_rfc) : ($inv->emisor_nombre ?: $inv->emisor_rfc) }}</td>
                            <td class="data text-muted" style="font-size:12px;">{{ $inv->serie }}{{ $inv->folio }}</td>
                            <td class="text-end data" style="font-weight:550;">${{ number_format($inv->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
