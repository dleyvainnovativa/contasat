{{-- Edit basic account info (name + RFC) for a client-owned account. Fields that
     affect posting/balanza (número, agrupador, naturaleza, nivel) are not edited here. --}}
<div class="modal fade" id="edit-account-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg); border-color:var(--border); background:var(--surface);">
            <div class="modal-body p-4">
                <h5 class="mb-1" style="font-weight:600;">Editar cuenta</h5>
                <div class="text-muted mb-3" id="ea-numero" style="font-size:12.5px;"></div>

                <div class="mb-2">
                    <label class="form-label">Nombre</label>
                    <input type="text" id="ea-nombre" class="form-control" maxlength="200">
                </div>
                <div class="mb-2">
                    <label class="form-label">RFC asociado (opcional)</label>
                    <input type="text" id="ea-rfc" class="form-control" maxlength="13">
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand btn-icon" id="ea-submit"><i class="fa-solid fa-check"></i> Guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script type="module">
(function () {
    let currentId = null;

    document.querySelectorAll('[data-edit-account]').forEach(btn => {
        btn.addEventListener('click', () => {
            currentId = btn.dataset.id;
            document.getElementById('ea-numero').textContent = btn.dataset.numero;
            document.getElementById('ea-nombre').value = btn.dataset.nombre || '';
            document.getElementById('ea-rfc').value = btn.dataset.rfc || '';
            App.modal.show('edit-account-modal');
        });
    });

    document.getElementById('ea-submit')?.addEventListener('click', async function () {
        const body = {
            nombre:  document.getElementById('ea-nombre').value.trim(),
            rfc_asociado: document.getElementById('ea-rfc').value.trim() || null,
        };
        if (!body.nombre) { App.toast.warning('El nombre es obligatorio.'); return; }
        await App.loading.button(this, async () => {
            try {
                const res = await App.http.put('{{ url('accounts') }}/' + currentId, body);
                App.toast.success(res.message);
                App.modal.hide('edit-account-modal');
                setTimeout(() => window.location.reload(), 700);
            } catch (e) { App.toast.error(e.message); }
        });
    });
})();
</script>
@endpush
