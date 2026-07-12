@extends('layouts.app')
@section('title', 'Facturas · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Facturas</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
    <button class="btn btn-brand btn-icon" onclick="App.modal.show('upload-modal')">
        <i class="fa-solid fa-cloud-arrow-up"></i> Cargar CFDI
    </button>
</div>

{{-- Totals strip: emitidas (income) vs recibidas (expense) --}}
<div class="row g-3 mb-4">
    <div class="col-sm-4" data-reveal>
        <div class="stat-card">
            <span class="stat-card__label"><i class="fa-solid fa-arrow-up-right-dots" style="color:var(--ok)"></i> Ingresos (emitidas)</span>
            <span class="stat-card__value">${{ number_format($totals['emitidas'], 2) }}</span>
        </div>
    </div>
    <div class="col-sm-4" data-reveal data-reveal-delay="40">
        <div class="stat-card">
            <span class="stat-card__label"><i class="fa-solid fa-arrow-down-right-dots" style="color:var(--warn)"></i> Gastos (recibidas)</span>
            <span class="stat-card__value">${{ number_format($totals['recibidas'], 2) }}</span>
        </div>
    </div>
    <div class="col-sm-4" data-reveal data-reveal-delay="80">
        <div class="stat-card">
            <span class="stat-card__label"><i class="fa-solid fa-file-lines" style="color:var(--brand-500)"></i> Total de facturas</span>
            <span class="stat-card__value">{{ $totals['count'] }}</span>
        </div>
    </div>
</div>

{{-- Recent uploads (shows async progress) --}}
@if($recentUploads->isNotEmpty())
    <div class="card-clean mb-4" data-reveal id="uploads-panel">
        <div class="card-clean__head"><strong>Cargas recientes</strong></div>
        <div class="card-clean__body" style="padding-top:.5rem; padding-bottom:.5rem;">
            @foreach($recentUploads as $up)
                <div class="d-flex align-items-center gap-3 py-2" data-upload-row="{{ $up->id }}" style="border-bottom:1px solid var(--border);">
                    <i class="fa-solid fa-file-zipper text-muted"></i>
                    <div class="flex-grow-1" style="min-width:0;">
                        <div class="text-truncate" style="font-size:13.5px; font-weight:500;">{{ $up->original_name }}</div>
                        <div class="text-muted" style="font-size:12px;" data-upload-detail>
                            @if($up->status === 'done')
                                {{ $up->imported }} importadas · {{ $up->skipped }} omitidas @if($up->failed) · {{ $up->failed }} con error @endif
                            @elseif($up->status === 'failed')
                                {{ $up->fatal_error }}
                            @else
                                En proceso…
                            @endif
                        </div>
                    </div>
                    <span class="badge-status s-{{ $up->statusColor() }}" data-upload-badge>
                        <i class="fa-solid fa-circle" style="font-size:8px;"></i> {{ $up->statusLabel() }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>
@endif

{{-- Invoice table --}}
<div class="card-clean" data-reveal>
    <div class="card-clean__head">
        <form method="GET" action="{{ route('invoices.index') }}" class="d-flex align-items-center gap-2 w-100">
            <div class="position-relative flex-grow-1" style="max-width:340px;">
                <i class="fa-solid fa-magnifying-glass position-absolute text-muted" style="left:.75rem; top:50%; transform:translateY(-50%); font-size:13px;"></i>
                <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="RFC, nombre, folio o UUID" style="padding-left:2.25rem;">
            </div>
            <select name="tipo" class="form-select" style="width:auto;" onchange="this.form.submit()">
                <option value="">Todas</option>
                <option value="emitida" @selected($tipo === 'emitida')>Emitidas</option>
                <option value="recibida" @selected($tipo === 'recibida')>Recibidas</option>
            </select>
        </form>
    </div>

    @if($invoices->isEmpty())
        <div class="empty-state">
            <i class="fa-solid fa-file-circle-plus"></i>
            <h3>Sin facturas</h3>
            <p>Carga un ZIP de CFDI descargado del SAT para empezar.</p>
            <button class="btn btn-brand btn-icon mt-2" onclick="App.modal.show('upload-modal')">
                <i class="fa-solid fa-cloud-arrow-up"></i> Cargar CFDI
            </button>
        </div>
    @else
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Contraparte</th>
                        <th>Folio</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Total</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        <tr>
                            <td class="data" style="font-size:13px;">{{ $invoice->fecha_emision?->format('d/m/Y') }}</td>
                            <td>
                                @if($invoice->tipo === 'emitida')
                                    <span class="badge-status s-success"><i class="fa-solid fa-arrow-up"></i> Emitida</span>
                                @else
                                    <span class="badge-status s-warning"><i class="fa-solid fa-arrow-down"></i> Recibida</span>
                                @endif
                            </td>
                            <td>
                                <div style="font-size:13.5px; font-weight:500;" class="text-truncate" style="max-width:260px;">
                                    {{ $invoice->tipo === 'emitida' ? ($invoice->receptor_nombre ?: $invoice->receptor_rfc) : ($invoice->emisor_nombre ?: $invoice->emisor_rfc) }}
                                </div>
                                <div class="data text-muted" style="font-size:11.5px;">
                                    {{ $invoice->tipo === 'emitida' ? $invoice->receptor_rfc : $invoice->emisor_rfc }}
                                </div>
                            </td>
                            <td class="data text-muted" style="font-size:12.5px;">{{ $invoice->serie }}{{ $invoice->folio }}</td>
                            <td class="text-end data">${{ number_format($invoice->subtotal, 2) }}</td>
                            <td class="text-end data" style="font-weight:550;">${{ number_format($invoice->total, 2) }}</td>
                            <td class="text-end">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-soft" style="padding:.35rem .6rem; font-size:12.5px;">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="mt-3">{{ $invoices->links() }}</div>

@include('invoices._upload_modal')
@endsection

@push('scripts')
<script>
// Upload flow: submit file -> poll status -> refresh list when done.
(function () {
    const form = document.getElementById('upload-form');
    const fileInput = document.getElementById('cfdi-file');
    const dropzone = document.getElementById('dropzone');
    const submitBtn = document.getElementById('upload-submit');
    const fileLabel = document.getElementById('file-label');

    if (!form) return;

    // Dropzone interactions
    dropzone.addEventListener('click', () => fileInput.click());
    ['dragover', 'dragenter'].forEach(ev => dropzone.addEventListener(ev, (e) => {
        e.preventDefault(); dropzone.classList.add('dragging');
    }));
    ['dragleave', 'drop'].forEach(ev => dropzone.addEventListener(ev, (e) => {
        e.preventDefault(); dropzone.classList.remove('dragging');
    }));
    dropzone.addEventListener('drop', (e) => {
        if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; updateLabel(); }
    });
    fileInput.addEventListener('change', updateLabel);

    function updateLabel() {
        fileLabel.textContent = fileInput.files.length ? fileInput.files[0].name : 'Arrastra un ZIP o XML, o haz clic para elegir';
    }

    submitBtn.addEventListener('click', async () => {
        if (!fileInput.files.length) { App.toast.warning('Elige un archivo primero.'); return; }

        const fd = new FormData();
        fd.append('archivo', fileInput.files[0]);

        await App.loading.button(submitBtn, async () => {
            try {
                const res = await App.http.post('{{ route('invoices.upload') }}', fd);
                App.toast.info(res.message || 'Procesando…');
                App.modal.hide('upload-modal');
                pollStatus(res.upload_id);
            } catch (err) {
                App.toast.error(err.message || 'No se pudo cargar el archivo.');
            }
        });
    });

    async function pollStatus(id) {
        const url = '{{ url('invoices/uploads') }}/' + id + '/status';
        let tries = 0;
        const timer = setInterval(async () => {
            tries++;
            try {
                const s = await App.http.get(url);
                if (s.finished) {
                    clearInterval(timer);
                    if (s.status === 'done') {
                        App.toast.success(`${s.imported} importadas, ${s.skipped} omitidas` + (s.failed ? `, ${s.failed} con error` : ''));
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        App.toast.error(s.fatal || 'La carga falló.');
                    }
                }
            } catch (e) { /* keep polling */ }
            if (tries > 150) clearInterval(timer); // ~5 min ceiling
        }, 2000);
    }
})();
</script>
@endpush
