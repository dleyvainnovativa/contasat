{{-- Movements whose date falls outside the statement window — likely belong to a
     neighboring period. Shown so nothing is silently dropped. --}}
<div class="card-clean">
    <div class="card-clean__head">
        <strong>Movimientos fuera de periodo</strong>
        <span class="text-muted" style="font-size:13px;">Su fecha cae fuera del rango del estado de cuenta</span>
    </div>
    <div class="table-responsive">
        <table class="table-clean">
            <thead><tr><th>Fecha</th><th>Concepto</th><th class="text-end">Monto</th></tr></thead>
            <tbody>
                @foreach($fueraPeriodo as $mov)
                    <tr>
                        <td class="data" style="font-size:13px; white-space:nowrap;">{{ $mov->fecha?->format('d/m/Y') }}</td>
                        <td style="font-size:13px;">{{ $mov->descripcion }}</td>
                        <td class="text-end data">${{ number_format($mov->cargo > 0 ? $mov->cargo : $mov->deposito, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
