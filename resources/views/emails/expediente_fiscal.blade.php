<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color:#1a1a1a; line-height:1.5;">
    <div style="max-width:560px; margin:0 auto; padding:24px;">
        <p>Estimado(a) {{ $client->display_name }},</p>
        <p>Adjunto encontrará el Expediente Fiscal correspondiente al periodo
           <strong>{{ $period->label }}</strong>, con el resumen de su contabilidad.</p>
        <p>Quedamos a sus órdenes para cualquier aclaración.</p>
        <p style="margin-top:24px; color:#666;">Gracias,<br>{{ config('app.name') }}</p>
    </div>
</body>
</html>
