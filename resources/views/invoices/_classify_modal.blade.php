{{-- Per-invoice classification modal. Opened from an edit button on each row in
     the filtered views. Shows the RFC-derived counterparty account (read-only)
     and the abono account as an editable dropdown, then confirms via the
     InvoiceClassificationService::confirm() path.

     For income invoices (emitida, tipo I) it also embeds the asiento shortcut:
     generate the póliza de provisión and de cobro without leaving the list.
     This mirrors invoices/_provision_cobro.blade.php, driven by the same
     PolizaController endpoints. --}}
<div class="modal fade" id="classify-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
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

                {{-- ASIENTOS (income invoices only). Hidden until edit() reports the
                     invoice is eligible. Populated dynamically by the script below. --}}
                <div id="cl-asiento" class="mt-4 pt-3" style="display:none; border-top:1px solid var(--border);">
                    <h6 class="mb-1" style="font-weight:600;">Asientos contables</h6>
                    <p class="text-muted mb-3" style="font-size:12px;">
                        Genera la póliza de provisión y, cuando haya pago, la de cobro — sin salir de la lista.
                    </p>

                    <div class="row g-3">
                        {{-- PROVISIÓN --}}
                        <div class="col-lg-6">
                            <div class="card-clean" style="height:100%;">
                                <div class="card-clean__head">
                                    <strong style="font-size:13px;">Provisión</strong>
                                    <span id="cl-prov-badge" class="badge-status s-success" style="font-size:11px; display:none;">
                                        <i class="fa-solid fa-check"></i> Generada
                                    </span>
                                </div>
                                <div class="card-clean__body">
                                    <div id="cl-prov-pending">
                                        <p class="text-muted" style="font-size:12px;">Cuenta de ingreso por concepto:</p>
                                        <div id="cl-concept-rows"></div>
                                        <button class="btn btn-brand btn-icon w-100 justify-content-center mt-2"
                                            id="cl-gen-provision" style="font-size:12.5px;">
                                            <i class="fa-solid fa-file-circle-plus"></i> Generar provisión
                                        </button>
                                        <div class="form-hint" style="font-size:11px; margin-top:.4rem;">
                                            Confirma la clasificación antes de generar.
                                        </div>
                                    </div>
                                    <div id="cl-prov-done" class="text-muted" style="font-size:12px; display:none;">
                                        <i class="fa-solid fa-check" style="color:var(--ok);"></i> Póliza de provisión ya generada.
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- COBRO --}}
                        <div class="col-lg-6">
                            <div class="card-clean" style="height:100%;">
                                <div class="card-clean__head">
                                    <strong style="font-size:13px;">Cobro</strong>
                                    <span id="cl-cobro-badge" class="badge-status s-success" style="font-size:11px; display:none;">
                                        <i class="fa-solid fa-check"></i> Generada
                                    </span>
                                </div>
                                <div class="card-clean__body">
                                    {{-- Locked until provisión exists --}}
                                    <div id="cl-cobro-locked" class="text-muted" style="font-size:12px; display:none;">
                                        <i class="fa-solid fa-lock"></i> Genera primero la provisión.
                                    </div>

                                    {{-- Payment-source picker --}}
                                    <div id="cl-cobro-pending" style="display:none;">
                                        <div class="mb-2">
                                            <label class="form-label" style="font-size:12px;">Opción 1 — Fecha</label>
                                            <input type="date" id="cl-cobro-fecha" class="form-control" style="font-size:13px;">
                                        </div>
                                        <div class="mb-2" style="font-size:12px; color:var(--text-muted);">
                                            <label class="d-flex align-items-center gap-2" id="cl-lbl-complemento">
                                                <input type="radio" name="cl-cobro-origen" value="complemento">
                                                Opción 2 — Complemento de pago (coincidencia)
                                            </label>
                                            <label class="d-flex align-items-center gap-2 mt-1" id="cl-lbl-estado">
                                                <input type="radio" name="cl-cobro-origen" value="estado_cuenta">
                                                Opción 3 — Estado de cuenta
                                            </label>
                                            <label class="d-flex align-items-center gap-2 mt-1">
                                                <input type="radio" name="cl-cobro-origen" value="manual" checked>
                                                Manual (fecha ingresada)
                                            </label>
                                        </div>
                                        <div id="cl-cobro-coincidencia" style="font-size:12px; margin:.35rem 0;"></div>
                                        <button class="btn btn-brand btn-icon w-100 justify-content-center mt-2"
                                            id="cl-gen-cobro" style="font-size:12.5px;">
                                            <i class="fa-solid fa-file-circle-plus"></i> Generar cobro
                                        </button>
                                    </div>

                                    <div id="cl-cobro-done" class="text-muted" style="font-size:12px; display:none;">
                                        <i class="fa-solid fa-check" style="color:var(--ok);"></i> Póliza de cobro ya generada.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script type="module">
    (function() {
        const base = @json(url('invoices'));
        let currentId = null;
        let abonoChoices = null;        // the live abono Choices instance
        let conceptChoices = [];        // per-concepto Choices instances

        // Tear down any Choices instances from a previous open.
        function destroyChoices() {
            if (abonoChoices) { abonoChoices.destroy(); abonoChoices = null; }
            conceptChoices.forEach(c => c.destroy());
            conceptChoices = [];
        }

        function choicesOpts(placeholder) {
            return {
                searchEnabled: true,
                searchResultLimit: 20,
                shouldSort: false,
                itemSelectText: '',
                searchPlaceholderValue: 'Buscar cuenta…',
                noResultsText: 'Sin coincidencias',
                noChoicesText: 'No hay cuentas disponibles',
                placeholderValue: placeholder,
            };
        }

        async function openModal(invoiceId) {
            currentId = invoiceId;
            destroyChoices();
            try {
                const data = await App.http.get(`${base}/${invoiceId}/classify`);

                document.getElementById('cl-folio').textContent = data.invoice.folio || '—';
                document.getElementById('cl-contraparte').textContent = data.invoice.contraparte || '';
                document.getElementById('cl-contable').value = data.cuenta_contable || '(sin asignar)';

                // --- Abono select ---
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
                abonoChoices = new Choices(sel, choicesOpts('Selecciona una cuenta'));
                if (data.cuenta_abono_id != null) {
                    abonoChoices.setChoiceByValue(String(data.cuenta_abono_id));
                }

                // --- Asiento section ---
                setupAsiento(data.asiento);

                App.modal.show('classify-modal');
            } catch (e) {
                App.toast.error('No se pudo cargar la clasificación.');
            }
        }

        // Builds the provisión/cobro shortcut UI from the edit() payload. When the
        // invoice isn't income-eligible, the whole section stays hidden.
        function setupAsiento(a) {
            const wrap = document.getElementById('cl-asiento');
            if (!a || !a.eligible) { wrap.style.display = 'none'; return; }
            wrap.style.display = 'block';

            // ---- Provisión ----
            const provBadge   = document.getElementById('cl-prov-badge');
            const provPending = document.getElementById('cl-prov-pending');
            const provDone    = document.getElementById('cl-prov-done');

            if (a.has_provision) {
                provBadge.style.display = '';
                provPending.style.display = 'none';
                provDone.style.display = '';
            } else {
                provBadge.style.display = 'none';
                provPending.style.display = '';
                provDone.style.display = 'none';

                // Per-concepto revenue pickers.
                const rows = document.getElementById('cl-concept-rows');
                rows.innerHTML = '';
                a.lines.forEach(line => {
                    const div = document.createElement('div');
                    div.className = 'mb-2';
                    const label = document.createElement('div');
                    label.style.cssText = 'font-size:12px; margin-bottom:.25rem;';
                    label.textContent = `${(line.descripcion || '').slice(0, 40)} · $${Number(line.importe).toLocaleString('es-MX',{minimumFractionDigits:2})}`;
                    const s = document.createElement('select');
                    s.className = 'form-select cl-concept-account';
                    s.dataset.index = line.index;
                    s.style.fontSize = '12.5px';
                    const first = document.createElement('option');
                    first.value = ''; first.textContent = 'Cuenta de ingreso…';
                    s.appendChild(first);
                    a.revenue_accounts.forEach(acc => {
                        const o = document.createElement('option');
                        o.value = acc.id; o.textContent = `${acc.numero_cuenta} — ${acc.nombre}`;
                        s.appendChild(o);
                    });
                    div.appendChild(label); div.appendChild(s);
                    rows.appendChild(div);
                });
                document.querySelectorAll('.cl-concept-account').forEach(s => {
                    conceptChoices.push(new Choices(s, choicesOpts('Cuenta de ingreso…')));
                });
            }

            // ---- Cobro ----
            const cobroBadge   = document.getElementById('cl-cobro-badge');
            const cobroLocked  = document.getElementById('cl-cobro-locked');
            const cobroPending = document.getElementById('cl-cobro-pending');
            const cobroDone    = document.getElementById('cl-cobro-done');

            cobroBadge.style.display = a.has_cobro ? '' : 'none';

            if (a.has_cobro) {
                cobroLocked.style.display = 'none';
                cobroPending.style.display = 'none';
                cobroDone.style.display = '';
            } else if (!a.has_provision) {
                cobroLocked.style.display = '';
                cobroPending.style.display = 'none';
                cobroDone.style.display = 'none';
            } else {
                cobroLocked.style.display = 'none';
                cobroPending.style.display = '';
                cobroDone.style.display = 'none';

                // Default date = today; enable/disable source options per availability.
                document.getElementById('cl-cobro-fecha').value = new Date().toISOString().slice(0, 10);

                const compRadio = document.querySelector('#cl-lbl-complemento input');
                const compLabel = document.getElementById('cl-lbl-complemento');
                compRadio.disabled = !a.has_uuid;
                compLabel.style.opacity = a.has_uuid ? '' : '.5';

                const estRadio = document.querySelector('#cl-lbl-estado input');
                const estLabel = document.getElementById('cl-lbl-estado');
                estRadio.disabled = !a.has_statement;
                estLabel.style.opacity = a.has_statement ? '' : '.5';
                document.getElementById('cl-cobro-coincidencia').innerHTML = '';
                // Reset to manual each open.
                document.querySelector('input[name="cl-cobro-origen"][value="manual"]').checked = true;
            }
        }

        // ---- Classification confirm ----
        function readAbono() {
            return abonoChoices ? abonoChoices.getValue(true) : document.getElementById('cl-abono').value;
        }

        async function confirmClassification() {
            const abonoId = readAbono();
            if (!abonoId) { App.toast.warning('Selecciona una cuenta de abono.'); return false; }
            try {
                const res = await App.http.post(`${base}/${currentId}/classify`, { cuenta_abono_id: abonoId });
                App.toast.success(res.message);
                return true;
            } catch (e) {
                App.toast.error(e.message || 'No se pudo confirmar.');
                return false;
            }
        }

        const submit = document.getElementById('cl-submit');
        submit?.addEventListener('click', async () => {
            await App.loading.button(submit, async () => {
                if (await confirmClassification()) {
                    App.modal.hide('classify-modal');
                    setTimeout(() => window.location.reload(), 900);
                }
            });
        });

        // ---- Provisión ----
        document.getElementById('cl-gen-provision')?.addEventListener('click', async function () {
            // Ensure the classification is saved first (provisión needs the abono).
            if (!(await confirmClassification())) return;

            const accounts = {};
            document.querySelectorAll('.cl-concept-account').forEach(s => {
                if (s.value) accounts[s.dataset.index] = s.value;
            });
            await App.loading.button(this, async () => {
                try {
                    const res = await App.http.post(`${base}/${currentId}/provision`, { concept_accounts: accounts });
                    App.toast.success(res.message);
                    setTimeout(() => window.location.reload(), 900);
                } catch (e) { App.toast.error(e.message); }
            });
        });

        // ---- Cobro: coincidencia lookup on source change ----
        document.querySelectorAll('input[name="cl-cobro-origen"]').forEach(radio => {
            radio.addEventListener('change', async () => {
                const origen = radio.value;
                const box = document.getElementById('cl-cobro-coincidencia');
                const fecha = document.getElementById('cl-cobro-fecha');
                if (origen === 'manual') { box.innerHTML = ''; fecha.disabled = false; return; }
                try {
                    const res = await App.http.get(`${base}/${currentId}/cobro-candidates?origen=${origen}`);
                    if (res.found) {
                        const c = res.candidates[0];
                        box.innerHTML = `<span style="color:var(--ok);"><i class="fa-solid fa-check"></i> Coincidencia: ${c.fecha} · $${Number(c.monto).toLocaleString('es-MX',{minimumFractionDigits:2})}</span>`;
                        fecha.value = c.fecha;
                        fecha.disabled = true;
                    } else {
                        box.innerHTML = `<span style="color:var(--warn);"><i class="fa-solid fa-triangle-exclamation"></i> Sin coincidencia para esta opción.</span>`;
                        fecha.disabled = false;
                    }
                } catch (e) { box.innerHTML = ''; }
            });
        });

        // ---- Cobro: generate ----
        document.getElementById('cl-gen-cobro')?.addEventListener('click', async function () {
            const origen = document.querySelector('input[name="cl-cobro-origen"]:checked')?.value || 'manual';
            const body = { origen };
            if (origen === 'manual') {
                const fecha = document.getElementById('cl-cobro-fecha').value;
                if (!fecha) { App.toast.warning('Ingresa la fecha de pago.'); return; }
                body.fecha_pago = fecha;
            }
            await App.loading.button(this, async () => {
                try {
                    const res = await App.http.post(`${base}/${currentId}/cobro`, body);
                    App.toast.success(res.message);
                    setTimeout(() => window.location.reload(), 900);
                } catch (e) { App.toast.error(e.message); }
            });
        });

        document.querySelectorAll('[data-classify]').forEach(btn =>
            btn.addEventListener('click', () => openModal(btn.dataset.classify)));
    })();
</script>
@endpush