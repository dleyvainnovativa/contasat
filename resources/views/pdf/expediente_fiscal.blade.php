<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { color:#1a1a1a; font-size:12px; }
        h1 { font-size:20px; margin:0 0 4px; }
        .sub { color:#666; font-size:12px; margin-bottom:20px; }
        .box { border:1px solid #ddd; border-radius:6px; padding:14px 16px; margin-bottom:16px; }
        .box h2 { font-size:13px; text-transform:uppercase; letter-spacing:.03em; color:#888; margin:0 0 10px; }
        table { width:100%; border-collapse:collapse; }
        td { padding:5px 0; }
        td.r { text-align:right; font-variant-numeric:tabular-nums; }
        .kv td:first-child { color:#555; }
        .total-row td { border-top:2px solid #333; font-weight:bold; padding-top:8px; }
        .muted { color:#999; }
    </style>
</head>
<body>
    <h1>Expediente Fiscal</h1>
    <div class="sub">{{ $client->display_name }} · RFC {{ $client->rfc }} · Periodo {{ $period->label }}</div>

    <div class="box">
        <h2>Resumen del periodo</h2>
        <table class="kv">
            <tr><td>Facturas emitidas (ingresos)</td><td class="r">${{ number_format($ingresos, 2) }}</td></tr>
            <tr><td>Facturas recibidas (gastos)</td><td class="r">${{ number_format($gastos, 2) }}</td></tr>
            <tr><td>IVA trasladado</td><td class="r">${{ number_format($ivaTrasladado, 2) }}</td></tr>
            <tr><td>IVA acreditable</td><td class="r">${{ number_format($ivaAcreditable, 2) }}</td></tr>
            <tr class="total-row"><td>Balance (ingresos − gastos)</td><td class="r">${{ number_format($balance, 2) }}</td></tr>
        </table>
    </div>

    <div class="box">
        <h2>Comprobantes</h2>
        <table class="kv">
            <tr><td>Total de comprobantes en el periodo</td><td class="r">{{ number_format($invoiceCount) }}</td></tr>
            <tr><td>Movimientos bancarios</td><td class="r">{{ number_format($movementCount) }}</td></tr>
            <tr><td>Conciliados</td><td class="r">{{ number_format($matchedCount) }}</td></tr>
            <tr><td>Pendientes de conciliar</td><td class="r">{{ number_format($unmatchedCount) }}</td></tr>
        </table>
    </div>

    <p class="muted" style="margin-top:24px; font-size:10px;">
        Documento generado el {{ now()->format('d/m/Y H:i') }} · {{ config('app.name') }}
    </p>
</body>
</html>
