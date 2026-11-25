{{-- Two-step "Importar saldos iniciales" flow. Step 1 uploads the client's
     workbook (Código agrupador · Descripción · RFC · Saldo inicial). Step 2 shows
     a preview the user checks/unchecks before committing. Each row becomes a
     child of its agrupador; RFC matches update an existing account instead. --}}
<div class="modal fade" id="import-saldos-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius:var(--radius-lg); border-color:var(--border); background:var(--surface);">
            <div class="modal-body p-4">
                <h5 class="mb-1" style="font-weight:600;">Importar saldos iniciales</h5>
                <p class="text-muted mb-3" style="font-size:12.5px;">
                    Columnas esperadas: <span class="data">Código agrupador · Descripción · RFC · Saldo inicial</span>.
                    Cada fila se inserta como subcuenta del código agrupador, heredando naturaleza y nivel.
                </p>

                {{-- Step 1: upload --}}
                <div id="is-step-upload">
                    <input type="file" id="is-file" class="form-control" accept=".xlsx,.xls,.csv">
                    <div class="form-hint mt-2">
                        <i class="fa-solid fa-circle-info"></i>
                        Nada se guarda hasta confirmar la vista previa.
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-brand btn-icon" id="is-analyze">
                            <i class="fa-solid fa-magnifying-glass"></i> Analizar
                        </button>
                    </div>
                </div>

                {{-- Step 2: preview --}}
                <div id="is-step-preview" style="display:none;">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <label style="font-size:12.5px; cursor:pointer;">
                            <input type="checkbox" id="is-select-all"> Seleccionar todo
                        </label>
                        <span class="text-muted ms-auto" id="is-summary" style="font-size:12px;"></span>
                    </div>
                    <div class="table-responsive" style="max-height:48vh; overflow:auto;">
                        <table class="table-clean" style="font-size:12px;">
                            <thead>
                                <tr>
                                    <th style="width:30px;"></th>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>RFC</th>
                                    <th class="text-end">Saldo</th>
                                    <th>Periodo</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody id="is-rows"></tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button class="btn btn-soft" id="is-back"><i class="fa-solid fa-arrow-left"></i> Atrás</button>
                        <button class="btn btn-brand btn-icon" id="is-commit">
                            <i class="fa-solid fa-check"></i> Importar seleccionadas
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    let parsed = [];   // preview rows from the server

    const stepUpload  = document.getElementById('is-step-upload');
    const stepPreview = document.getElementById('is-step-preview');
    const rowsBody    = document.getElementById('is-rows');
    const summary     = document.getElementById('is-summary');

    function showUpload() {
        stepPreview.style.display = 'none';
        stepUpload.style.display = 'block';
    }

    document.querySelectorAll('[data-import-saldos]').forEach(b =>
        b.addEventListener('click', () => { showUpload(); App.modal.show('import-saldos-modal'); }));

    document.getElementById('is-back')?.addEventListener('click', showUpload);

    function badge(status) {
        switch (status) {
            case 'new':      return '<span class="badge-status s-success" style="font-size:10px;">Nueva</span>';
            case 'update':   return '<span class="badge-status s-info" style="font-size:10px;">Asignar saldo</span>';
            case 'conflict': return '<span class="badge-status s-warning" style="font-size:10px;">Conflicto: sobrescribir</span>';
            case 'error':    return '<span class="badge-status s-danger" style="font-size:10px;">Sin cuenta padre</span>';
            default:         return status;
        }
    }

    function render() {
        rowsBody.innerHTML = '';
        parsed.forEach((r, idx) => {
            const tr = document.createElement('tr');
            const disabled = r.importable ? '' : 'disabled';
            const checked  = r.default_checked ? 'checked' : '';
            const note = r.note ? `<div class="text-muted" style="font-size:10.5px;">${r.note}</div>` : '';
            tr.innerHTML = `
                <td class="text-center"><input type="checkbox" class="is-row" data-idx="${idx}" ${checked} ${disabled}></td>
                <td class="data">${r.codigo}${r.proposed_numero ? ' <span class="text-muted">→ ' + r.proposed_numero + '</span>' : ''}</td>
                <td>${r.descripcion || ''}</td>
                <td class="data text-muted">${r.rfc || '—'}</td>
                <td class="text-end data">${Number(r.saldo).toLocaleString('es-MX', {minimumFractionDigits: 2})}</td>
                <td class="data text-muted">${(r.month && r.year) ? String(r.month).padStart(2,'0') + '/' + r.year : '—'}</td>
                <td>${badge(r.status)}${note}</td>`;
            rowsBody.appendChild(tr);
        });
        rowsBody.querySelectorAll('.is-row').forEach(c => c.addEventListener('change', refreshSummary));
        refreshSummary();
    }

    function refreshSummary() {
        const n = rowsBody.querySelectorAll('.is-row:checked').length;
        const total = parsed.filter(r => r.importable).length;
        summary.textContent = `${n} de ${total} seleccionadas (${parsed.length} filas)`;
    }

    document.getElementById('is-select-all')?.addEventListener('change', function () {
        rowsBody.querySelectorAll('.is-row:not([disabled])').forEach(c => c.checked = this.checked);
        refreshSummary();
    });

    document.getElementById('is-analyze')?.addEventListener('click', async function () {
        const file = document.getElementById('is-file').files[0];
        if (!file) { App.toast.warning('Selecciona un archivo.'); return; }
        const fd = new FormData();
        fd.append('archivo', file);
        await App.loading.button(this, async () => {
            try {
                const res = await App.http.post('{{ route('accounts.import-saldos.preview') }}', fd);
                parsed = res.rows || [];
                if (!parsed.length) { App.toast.warning('No se encontraron filas válidas.'); return; }
                render();
                stepUpload.style.display = 'none';
                stepPreview.style.display = 'block';
            } catch (e) { App.toast.error(e.message); }
        });
    });

    document.getElementById('is-commit')?.addEventListener('click', async function () {
        const rows = [];
        rowsBody.querySelectorAll('.is-row:checked').forEach(c => {
            const r = parsed[c.dataset.idx];
            const action = r.status === 'conflict' ? 'override' : (r.status === 'update' ? 'update' : 'create');
            rows.push({ codigo: r.codigo, descripcion: r.descripcion, rfc: r.rfc, saldo: r.saldo, year: r.year, month: r.month, action });
        });
        if (!rows.length) { App.toast.warning('Selecciona al menos una cuenta.'); return; }
        await App.loading.button(this, async () => {
            try {
                const res = await App.http.post('{{ route('accounts.import-saldos.commit') }}', { rows });
                App.toast.success(res.message);
                App.modal.hide('import-saldos-modal');
                setTimeout(() => window.location.reload(), 1100);
            } catch (e) { App.toast.error(e.message); }
        });
    });
})();
</script>
@endpush
