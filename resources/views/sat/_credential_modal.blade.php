{{-- e.firma upload. The .cer/.key are validated (pair matches, password correct,
     is a FIEL not a CSD, RFC matches the client) before anything is persisted. --}}
<div class="modal fade" id="credential-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg); border-color:var(--border); background:var(--surface);">
            <div class="modal-body p-4">
                <h5 class="mb-1" style="font-weight:600;">Registrar e.firma</h5>
                <p class="text-muted mb-3" style="font-size:13px;">
                    Cliente: <strong id="cred-client-name"></strong>
                </p>

                <div class="mb-3">
                    <label class="form-label">Certificado (.cer)</label>
                    <input type="file" id="cer-file" class="form-control" accept=".cer">
                </div>
                <div class="mb-3">
                    <label class="form-label">Llave privada (.key)</label>
                    <input type="file" id="key-file" class="form-control" accept=".key">
                </div>
                <div class="mb-3">
                    <label class="form-label">Contraseña de la llave</label>
                    <input type="password" id="key-password" class="form-control" autocomplete="off">
                </div>

                <div class="form-hint" style="color:var(--warn);">
                    <i class="fa-solid fa-shield-halved"></i>
                    Debe ser <strong>e.firma</strong>, no un sello digital (CSD). Se almacena cifrada.
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand btn-icon" id="cred-submit"><i class="fa-solid fa-check"></i> Validar y guardar</button>
                </div>
            </div>
        </div>
    </div>
</div>
