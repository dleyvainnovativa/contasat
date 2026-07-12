@extends('layouts.app')
@section('title', 'Descarga SAT · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Descarga masiva SAT</h1>
        <div class="subtitle">Descarga automática de CFDI con e.firma</div>
    </div>
    @if($pending)
        <span class="badge-status s-warning" style="align-self:center;">
            <i class="fa-solid fa-hourglass-half"></i> {{ $pending }} en proceso
        </span>
    @endif
</div>

{{-- How it works + the two constraints that actually bite --}}
<div class="card-clean mb-4" data-reveal style="border-left:3px solid var(--brand-500);">
    <div class="card-clean__body">
        <div class="d-flex align-items-start gap-3">
            <i class="fa-solid fa-circle-info" style="font-size:1.25rem; color:var(--brand-500); margin-top:2px;"></i>
            <div style="font-size:13.5px;">
                <strong>Cómo funciona.</strong> Se presenta una solicitud al SAT, él prepara los paquetes, y el sistema
                los descarga y los importa automáticamente. <strong>La respuesta del SAT puede tardar de minutos a
                varias horas</strong> (excepcionalmente hasta 72). No hace falta esperar en esta pantalla.
                <div class="mt-2" style="color:var(--warn);">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    El SAT permite solicitar <strong>un mismo periodo solo dos veces en la vida</strong>.
                    Por eso el sistema nunca reenvía una solicitud ya hecha.
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Credentials per client --}}
<div class="card-clean mb-4" data-reveal>
    <div class="card-clean__head">
        <strong>e.firma por cliente</strong>
        <span class="text-muted" style="font-size:13px;">{{ $clients->whereNotNull('satCredential')->count() }} de {{ $clients->count() }} registradas</span>
    </div>
    <div class="table-responsive">
        <table class="table-clean">
            <thead>
                <tr><th>Cliente</th><th>RFC</th><th>e.firma</th><th>Vigencia</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
                @foreach($clients as $client)
                    @php $cred = $client->satCredential; @endphp
                    <tr>
                        <td style="font-weight:550;">{{ $client->display_name }}</td>
                        <td><span class="data text-muted">{{ $client->rfc }}</span></td>
                        <td>
                            @if($cred)
                                <span class="badge-status s-{{ $cred->statusColor() }}">
                                    <i class="fa-solid fa-certificate"></i> {{ $cred->statusLabel() }}
                                </span>
                            @else
                                <span class="badge-status s-secondary"><i class="fa-solid fa-circle-minus"></i> Sin registrar</span>
                            @endif
                        </td>
                        <td class="data text-muted" style="font-size:12.5px;">
                            {{ $cred?->valid_to?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="text-end">
                            @if($cred && $cred->isUsable())
                                <button class="btn btn-brand" style="padding:.3rem .6rem; font-size:12.5px;"
                                        data-download-client="{{ $client->id }}" data-client-name="{{ $client->display_name }}">
                                    <i class="fa-solid fa-cloud-arrow-down"></i> Descargar
                                </button>
                            @endif
                            <button class="btn btn-soft" style="padding:.3rem .6rem; font-size:12.5px;"
                                    data-credential-client="{{ $client->id }}" data-client-name="{{ $client->display_name }}">
                                <i class="fa-solid fa-{{ $cred ? 'pen' : 'upload' }}"></i>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Request history --}}
<div class="card-clean" data-reveal>
    <div class="card-clean__head"><strong>Solicitudes</strong></div>
    @if($requests->isEmpty())
        <div class="empty-state">
            <i class="fa-solid fa-cloud-arrow-down"></i>
            <h3>Sin solicitudes</h3>
            <p>Registra una e.firma y solicita la descarga de un periodo.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr><th>Cliente</th><th>Periodo</th><th>Tipo</th><th>Estado</th><th class="text-end">CFDI</th><th class="text-end">Importadas</th></tr>
                </thead>
                <tbody>
                    @foreach($requests as $req)
                        <tr data-request-row="{{ $req->id }}">
                            <td style="font-weight:550;">{{ $req->client->display_name }}</td>
                            <td class="data" style="font-size:12.5px;">{{ $req->period_start->format('M Y') }}</td>
                            <td style="font-size:12.5px;">{{ $req->typeLabel() }}</td>
                            <td>
                                <span class="badge-status s-{{ $req->statusColor() }}" data-request-badge>
                                    <i class="fa-solid fa-circle" style="font-size:8px;"></i> {{ $req->statusLabel() }}
                                </span>
                                @if($req->error_message)
                                    <div class="text-muted" style="font-size:11px; margin-top:.2rem;">{{ \Illuminate\Support\Str::limit($req->error_message, 60) }}</div>
                                @endif
                            </td>
                            <td class="text-end data">{{ $req->cfdi_count ?: '—' }}</td>
                            <td class="text-end data">{{ $req->status === 'completado' ? $req->imported : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@include('sat._credential_modal')
@include('sat._download_modal')
@endsection

@push('scripts')
<script>
(function () {
    let activeClientId = null;

    // --- Credential upload ---
    document.querySelectorAll('[data-credential-client]').forEach(btn => {
        btn.addEventListener('click', () => {
            activeClientId = btn.dataset.credentialClient;
            document.getElementById('cred-client-name').textContent = btn.dataset.clientName;
            App.modal.show('credential-modal');
        });
    });

    const credBtn = document.getElementById('cred-submit');
    credBtn?.addEventListener('click', async () => {
        const cer = document.getElementById('cer-file').files[0];
        const key = document.getElementById('key-file').files[0];
        const pwd = document.getElementById('key-password').value;
        if (!cer || !key || !pwd) { App.toast.warning('Sube el .cer, el .key y la contraseña.'); return; }

        const fd = new FormData();
        fd.append('cer', cer); fd.append('key', key); fd.append('password', pwd);

        await App.loading.button(credBtn, async () => {
            try {
                const res = await App.http.post(`{{ url('sat/clients') }}/${activeClientId}/credential`, fd);
                App.toast.success(res.message);
                App.modal.hide('credential-modal');
                setTimeout(() => window.location.reload(), 1200);
            } catch (e) { App.toast.error(e.message); }
        });
    });

    // --- Download request ---
    document.querySelectorAll('[data-download-client]').forEach(btn => {
        btn.addEventListener('click', () => {
            activeClientId = btn.dataset.downloadClient;
            document.getElementById('dl-client-name').textContent = btn.dataset.clientName;
            App.modal.show('download-modal');
        });
    });

    const dlBtn = document.getElementById('dl-submit');
    dlBtn?.addEventListener('click', async () => {
        const body = {
            year: document.getElementById('dl-year').value,
            month: document.getElementById('dl-month').value,
            download_type: document.getElementById('dl-direction').value,
            request_type: document.getElementById('dl-kind').value,
        };
        await App.loading.button(dlBtn, async () => {
            try {
                const res = await App.http.post(`{{ url('sat/clients') }}/${activeClientId}/download`, body);
                App.toast.success(res.message);
                App.modal.hide('download-modal');
                setTimeout(() => window.location.reload(), 1500);
            } catch (e) { App.toast.error(e.message); }
        });
    });

    // --- Live status refresh for pending requests ---
    const pendingRows = document.querySelectorAll('[data-request-row]');
    if (pendingRows.length) {
        setInterval(async () => {
            for (const row of pendingRows) {
                const badge = row.querySelector('[data-request-badge]');
                if (!badge || badge.textContent.includes('Completado') || badge.textContent.includes('Error')) continue;
                try {
                    const s = await App.http.get(`{{ url('sat/requests') }}/${row.dataset.requestRow}/status`);
                    badge.className = `badge-status s-${s.color}`;
                    badge.innerHTML = `<i class="fa-solid fa-circle" style="font-size:8px;"></i> ${s.label}`;
                } catch (e) { /* ignore */ }
            }
        }, 30000); // SAT is slow; polling the UI fast buys nothing
    }
})();
</script>
@endpush
