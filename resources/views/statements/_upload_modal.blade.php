{{-- Bank statement PDF upload modal. --}}
<div class="modal fade" id="statement-upload-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg); border-color:var(--border); background:var(--surface);">
            <div class="modal-body p-4">
                <h5 class="mb-1" style="font-weight:600;">Cargar estado de cuenta</h5>
                <p class="text-muted mb-3" style="font-size:13px;">
                    Sube el PDF del estado de cuenta. La extracción detecta el banco y valida que el balance cuadre.
                </p>
                <div id="statement-dropzone" class="dropzone">
                    <i class="fa-solid fa-file-pdf"></i>
                    <div id="statement-file-label">Arrastra el PDF del estado de cuenta</div>
                    <input type="file" id="statement-file" accept=".pdf" hidden>
                </div>
                <div class="form-hint mt-2">
                    <i class="fa-solid fa-circle-info"></i> Sólo PDFs con texto. Los estados escaneados (imagen) aún no están soportados.
                </div>
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand btn-icon" id="statement-upload-submit"><i class="fa-solid fa-wand-magic-sparkles"></i> Extraer</button>
                </div>
            </div>
        </div>
    </div>
</div>
