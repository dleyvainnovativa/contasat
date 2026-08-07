{{-- Per-invoice classification modal. Opened from an edit button on each row in
     the filtered views. Shows the RFC-derived counterparty account (read-only)
     and the abono account as an editable dropdown, then confirms via the
     InvoiceClassificationService::confirm() path. --}}
<div class="modal fade" id="classify-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg); border-color:var(--border); background:var(--surface);">
            <div class="modal-body p-4">
                <h5 class="mb-1" style="font-weight:600;">Clasificar factura</h5>
                <p class="text-muted mb-3" style="font-size:13px;">
                    <span id="cl-folio" class="data"></span> · <span id="cl-contraparte"></span>
                </p>

                <div class="mb-3">
                    <label class="form-label">Cuenta contable (contraparte)</label>
                    <input type="text" id="cl-contable" class="form-control" readonly
                        style="background:var(--surface-2);">
                    <div class="form-hint" style="font-size:11.5px;">
                        <i class="fa-solid fa-lock"></i> Se asigna por RFC automáticamente.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Cuenta de abono</label>
                    <select id="cl-abono" class="form-select">
                        <option value="">— Selecciona —</option>
                    </select>
                    <div class="form-hint" style="font-size:11.5px;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> La IA sugiere; tú confirmas.
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand btn-icon" id="cl-submit">
                        <i class="fa-solid fa-check"></i> Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script type="module">
    (function() {
        let currentId = null;
        let abonoChoices = null; // the live Choices instance

        async function openModal(invoiceId) {
            currentId = invoiceId;
            try {
                const data = await App.http.get(`{{ url('invoices') }}/${invoiceId}/classify`);

                document.getElementById('cl-folio').textContent = data.invoice.folio || '—';
                document.getElementById('cl-contraparte').textContent = data.invoice.contraparte || '';
                document.getElementById('cl-contable').value = data.cuenta_contable || '(sin asignar)';

                // Tear down any prior instance before rebuilding with fresh options.
                if (abonoChoices) {
                    abonoChoices.destroy();
                    abonoChoices = null;
                }

                const sel = document.getElementById('cl-abono');
                sel.innerHTML = '<option value="">— Selecciona —</option>';
                data.candidates.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = `${c.numero_cuenta} — ${c.nombre}`;
                    if (data.cuenta_abono_id != null && String(c.id) === String(data.cuenta_abono_id)) {
                        opt.selected = true;
                    }
                    sel.appendChild(opt);
                });

                // Now that the options exist, enhance the select.
                abonoChoices = new Choices(sel, {
                    searchEnabled: true,
                    searchResultLimit: 20,
                    shouldSort: false,
                    itemSelectText: '',
                    searchPlaceholderValue: 'Buscar cuenta…',
                    noResultsText: 'Sin coincidencias',
                    noChoicesText: 'No hay cuentas disponibles',
                    placeholderValue: 'Selecciona una cuenta',
                });
                if (data.cuenta_abono_id != null) {
                    abonoChoices.setChoiceByValue(String(data.cuenta_abono_id));
                }

                App.modal.show('classify-modal');
            } catch (e) {
                App.toast.error('No se pudo cargar la clasificación.');
            }
        }

        document.querySelectorAll('[data-classify]').forEach(btn =>
            btn.addEventListener('click', () => openModal(btn.dataset.classify)));

        const submit = document.getElementById('cl-submit');
        submit?.addEventListener('click', async () => {
            // Read from the Choices instance, not the raw select.
            const abonoId = abonoChoices ? abonoChoices.getValue(true) : document.getElementById('cl-abono').value;
            if (!abonoId) {
                App.toast.warning('Selecciona una cuenta de abono.');
                return;
            }

            await App.loading.button(submit, async () => {
                try {
                    const res = await App.http.post(`{{ url('invoices') }}/${currentId}/classify`, {
                        cuenta_abono_id: abonoId,
                    });
                    App.toast.success(res.message);
                    App.modal.hide('classify-modal');
                    setTimeout(() => window.location.reload(), 900);
                } catch (e) {
                    App.toast.error(e.message || 'No se pudo confirmar.');
                }
            });
        });
    })();
</script>
@endpush