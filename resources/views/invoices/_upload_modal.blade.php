{{-- CFDI upload modal with a drag-and-drop zone. Submits via app.js (no <form> POST). --}}
<div class="modal fade" id="upload-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg); border-color:var(--border); background:var(--surface);">
            <div class="modal-body p-4">
                <h5 class="mb-1" style="font-weight:600;">Cargar CFDI</h5>
                <p class="text-muted mb-3" style="font-size:13px;">
                    Sube el ZIP descargado del SAT o un XML individual. Se omiten automáticamente los duplicados.
                </p>

                <div id="upload-form">
                    <div id="dropzone" class="dropzone">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <div id="file-label">Arrastra un ZIP o XML, o haz clic para elegir</div>
                        <input type="file" id="cfdi-file" accept=".zip,.xml" hidden>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-brand btn-icon" id="upload-submit">
                            <i class="fa-solid fa-check"></i> Procesar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
