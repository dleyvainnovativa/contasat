{{-- Shared confirmation modal, driven by App.modal.confirm(). --}}
<div class="modal fade" id="confirm-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: var(--radius-lg); border-color: var(--border); background: var(--surface);">
            <div class="modal-body p-4">
                <h5 class="mb-2" data-confirm-title style="font-weight:600;">Confirmar</h5>
                <p class="mb-4 text-muted" data-confirm-message></p>
                <div class="d-flex justify-content-end gap-2">
                    <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand" data-confirm-ok>Confirmar</button>
                </div>
            </div>
        </div>
    </div>
</div>
