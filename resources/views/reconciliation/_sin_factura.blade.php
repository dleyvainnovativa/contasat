{{-- Bank movements with no matching invoice. Each can be manually linked to an
     invoice, or flagged as a question for the client. --}}
<div class="card-clean">
    <div class="card-clean__head">
        <strong>Movimientos sin factura</strong>
        <span class="text-muted" style="font-size:13px;">Movimientos del banco sin CFDI que los respalde</span>
    </div>
    @if($sinFactura->isEmpty())
        <div class="empty-state"><i class="fa-solid fa-check"></i><h3>Todo enlazado</h3><p>No hay movimientos sin factura.</p></div>
    @else
        <div class="table-responsive">
            <table class="table-clean">
                <thead><tr><th>Fecha</th><th>Concepto</th><th class="text-end">Monto</th><th style="min-width:220px;">Enlazar a factura</th></tr></thead>
                <tbody>
                    @foreach($sinFactura as $mov)
                        <tr>
                            <td class="data" style="font-size:13px; white-space:nowrap;">{{ $mov->fecha?->format('d/m/Y') }}</td>
                            <td style="font-size:13px;">{{ $mov->descripcion }}</td>
                            <td class="text-end data" style="font-weight:550; color:{{ $mov->cargo > 0 ? 'var(--warn)' : 'var(--ok)' }};">
                                ${{ number_format($mov->cargo > 0 ? $mov->cargo : $mov->deposito, 2) }}
                            </td>
                            <td>
                                <select class="form-select" style="font-size:12.5px; padding:.3rem .5rem;" data-link-select="{{ $mov->id }}">
                                    <option value="">— Buscar factura —</option>
                                    @foreach($sinMovimiento as $inv)
                                        <option value="{{ $inv->id }}">
                                            {{ $inv->fecha_emision?->format('d/m') }} · ${{ number_format($inv->total, 2) }} · {{ \Illuminate\Support\Str::limit($inv->tipo === 'emitida' ? $inv->receptor_nombre : $inv->emisor_nombre, 24) }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
