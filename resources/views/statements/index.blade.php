@extends('layouts.app')
@section('title', 'Estados de cuenta · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Estados de cuenta</h1>
        <div class="subtitle">{{ $period->client->display_name }} · {{ $period->label }}</div>
    </div>
    <button class="btn btn-brand btn-icon" onclick="App.modal.show('statement-upload-modal')">
        <i class="fa-solid fa-cloud-arrow-up"></i> Cargar PDF
    </button>
</div>

<div class="card-clean" data-reveal>
    <div class="card-clean__head">
        <strong>Documentos del periodo</strong>
        <span class="text-muted" style="font-size:13px;">{{ $statements->count() }} estados</span>
    </div>

    @if($statements->isEmpty())
        <div class="empty-state">
            <i class="fa-solid fa-building-columns"></i>
            <h3>Sin estados de cuenta</h3>
            <p>Carga el PDF del estado de cuenta del cliente. La extracción es automática.</p>
            <button class="btn btn-brand btn-icon mt-2" onclick="App.modal.show('statement-upload-modal')">
                <i class="fa-solid fa-cloud-arrow-up"></i> Cargar PDF
            </button>
        </div>
    @else
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Banco</th>
                        <th>Periodo</th>
                        <th>Estado</th>
                        <th>Balance</th>
                        <th class="text-end">Movimientos</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($statements as $st)
                        <tr data-statement-row="{{ $st->id }}">
                            <td>
                                <span style="font-weight:550;">{{ $st->banco ?: '—' }}</span>
                                @if($st->numero_cuenta)
                                    <span class="data text-muted d-block" style="font-size:11.5px;">{{ $st->numero_cuenta }}</span>
                                @endif
                            </td>
                            <td class="data text-muted" style="font-size:12.5px;">
                                @if($st->fecha_inicio){{ $st->fecha_inicio->format('d/m/y') }} – {{ $st->fecha_fin?->format('d/m/y') }}@else—@endif
                            </td>
                            <td>
                                <span class="badge-status s-{{ $st->statusColor() }}" data-statement-badge>
                                    <i class="fa-solid fa-circle" style="font-size:8px;"></i> {{ $st->statusLabel() }}
                                </span>
                            </td>
                            <td>
                                @if($st->extraccion_status === 'ok' && $st->balance_cuadra)
                                    <span class="badge-status s-success"><i class="fa-solid fa-check"></i> Cuadra</span>
                                @elseif($st->extraccion_status === 'revision')
                                    <span class="badge-status s-warning"><i class="fa-solid fa-triangle-exclamation"></i> No cuadra</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end data">{{ $st->movements_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('statements.show', $st) }}" class="btn btn-soft" style="padding:.35rem .6rem; font-size:12.5px;">
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

@include('statements._upload_modal')
@endsection

@push('scripts')
<script>
(function () {
    const fileInput = document.getElementById('statement-file');
    const dropzone = document.getElementById('statement-dropzone');
    const submitBtn = document.getElementById('statement-upload-submit');
    const fileLabel = document.getElementById('statement-file-label');
    if (!dropzone) return;

    dropzone.addEventListener('click', () => fileInput.click());
    ['dragover','dragenter'].forEach(ev => dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.add('dragging'); }));
    ['dragleave','drop'].forEach(ev => dropzone.addEventListener(ev, e => { e.preventDefault(); dropzone.classList.remove('dragging'); }));
    dropzone.addEventListener('drop', e => { if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; updateLabel(); } });
    fileInput.addEventListener('change', updateLabel);
    function updateLabel() { fileLabel.textContent = fileInput.files.length ? fileInput.files[0].name : 'Arrastra el PDF del estado de cuenta'; }

    submitBtn.addEventListener('click', async () => {
        if (!fileInput.files.length) { App.toast.warning('Elige un archivo PDF primero.'); return; }
        const fd = new FormData();
        fd.append('archivo', fileInput.files[0]);
        await App.loading.button(submitBtn, async () => {
            try {
                const res = await App.http.post('{{ route('statements.upload') }}', fd);
                App.toast.info(res.message || 'Extrayendo…');
                App.modal.hide('statement-upload-modal');
                pollStatus(res.statement_id);
            } catch (err) {
                App.toast.error(err.message || 'No se pudo cargar el PDF.');
            }
        });
    });

    async function pollStatus(id) {
        const url = '{{ url('statements') }}/' + id + '/status';
        let tries = 0;
        const timer = setInterval(async () => {
            tries++;
            try {
                const s = await App.http.get(url);
                if (s.finished) {
                    clearInterval(timer);
                    if (s.status === 'ok') App.toast.success('Extraído. El balance cuadra.');
                    else if (s.status === 'revision') App.toast.warning('Extraído, pero el balance no cuadra. Requiere revisión.');
                    else App.toast.error(s.error || 'La extracción falló.');
                    setTimeout(() => window.location.reload(), 1400);
                }
            } catch (e) { /* keep polling */ }
            if (tries > 150) clearInterval(timer);
        }, 2500);
    }
})();
</script>
@endpush
