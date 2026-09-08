{{-- Per-invoice classification modal, opened from the edit button on each row.

     Flow: the counterparty account (RFC-derived) is shown read-only. A master
     "cuenta de abono" applies to every line; each concepto line can override with
     its own account. One Confirmar button at the bottom saves the classification
     and — for income invoices — generates the póliza de provisión in the same
     action (respecting per-line accounts). Gasto invoices classify only for now.

     Backed by InvoiceClassificationController@edit / @update. --}}
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
                    <label class="form-label" id="cl-abono-label">Cuenta de abono (todas las líneas)</label>
                    <select id="cl-abono" class="form-select">
                        <option value="">— Selecciona —</option>
                    </select>
                    <div class="form-hint" style="font-size:11.5px;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        Aplica a todas las líneas. Ajusta una línea abajo si necesita otra cuenta.
                    </div>
                </div>

                {{-- Per-line overrides. Each concepto inherits the master account
                     unless given its own. Populated by the script. --}}
                <div class="mb-2">
                    <label class="form-label" style="font-size:12.5px;">Cuenta por línea</label>
                    <div id="cl-lines" style="max-height:38vh; overflow:auto;"></div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3" style="border-top:1px solid var(--border);">
                    <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand btn-icon" id="cl-submit">
                        <i class="fa-solid fa-check"></i> <span id="cl-submit-label">Confirmar</span>
                    </button>
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
        let masterChoices = null; // master abono Choices instance
        let lineChoices = []; // per-line Choices instances
        let candidates = []; // account options for this invoice

        function destroyChoices() {
            if (masterChoices) {
                masterChoices.destroy();
                masterChoices = null;
            }
            lineChoices.forEach(c => c.destroy());
            lineChoices = [];
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

        function optionsHtml(selectedId) {
            let html = '<option value="">— Hereda de la cuenta principal —</option>';
            candidates.forEach(c => {
                const sel = (selectedId != null && String(c.id) === String(selectedId)) ? ' selected' : '';
                html += `<option value="${c.id}"${sel}>${c.numero_cuenta} — ${c.nombre}</option>`;
            });
            return html;
        }

        async function openModal(invoiceId) {
            currentId = invoiceId;
            destroyChoices();
            try {
                const data = await App.http.get(`${base}/${invoiceId}/classify`);
                candidates = data.candidates || [];

                document.getElementById('cl-folio').textContent = data.invoice.folio || '—';
                document.getElementById('cl-contraparte').textContent = data.invoice.contraparte || '';
                document.getElementById('cl-contable').value = data.cuenta_contable || '(sin asignar)';

                // Labels adapt to income vs expense.
                const isIncome = data.invoice.is_income;
                document.getElementById('cl-abono-label').textContent =
                    isIncome ? 'Cuenta de ingreso (todas las líneas)' : 'Cuenta de gasto (todas las líneas)';

                // Confirm label tells the user what will happen.
                const submitLabel = document.getElementById('cl-submit-label');
                if (isIncome) {
                    submitLabel.textContent = data.has_provision ? 'Confirmar' : 'Confirmar y generar póliza';
                } else {
                    submitLabel.textContent = 'Confirmar';
                }

                // --- Master select ---
                const sel = document.getElementById('cl-abono');
                sel.innerHTML = '<option value="">— Selecciona —</option>' +
                    candidates.map(c => `<option value="${c.id}">${c.numero_cuenta} — ${c.nombre}</option>`).join('');
                masterChoices = new Choices(sel, choicesOpts('Selecciona una cuenta'));
                if (data.cuenta_abono_id != null) {
                    masterChoices.setChoiceByValue(String(data.cuenta_abono_id));
                }

                // --- Per-line rows ---
                const wrap = document.getElementById('cl-lines');
                wrap.innerHTML = '';
                const showNoDed = !isIncome; // non-deductible applies to gasto
                (data.lines || []).forEach(line => {
                    const row = document.createElement('div');
                    row.className = 'd-flex align-items-center gap-2 mb-2';
                    row.dataset.index = line.index;

                    const label = document.createElement('div');
                    label.style.cssText = 'flex:1; min-width:0; font-size:12px;';
                    label.className = 'text-truncate text-muted';
                    label.title = line.descripcion || '';
                    label.textContent = `${(line.descripcion || '(sin descripción)')} · $${Number(line.importe).toLocaleString('es-MX',{minimumFractionDigits:2})}`;

                    const s = document.createElement('select');
                    s.className = 'form-select cl-line-account';
                    s.style.cssText = 'flex:0 0 260px; max-width:260px; font-size:12.5px;';
                    s.dataset.index = line.index;
                    s.innerHTML = optionsHtml(line.cuenta_abono_id);

                    row.appendChild(label);
                    row.appendChild(s);

                    if (showNoDed) {
                        const nd = document.createElement('input');
                        nd.type = 'number';
                        nd.step = '0.01';
                        nd.min = '0';
                        nd.className = 'form-control cl-line-noded';
                        nd.style.cssText = 'flex:0 0 110px; max-width:110px; font-size:12px;';
                        nd.placeholder = 'No ded.';
                        nd.title = 'Parte no deducible';
                        nd.dataset.index = line.index;
                        nd.value = line.parte_no_deducible ? line.parte_no_deducible : '';
                        row.appendChild(nd);
                    }

                    wrap.appendChild(row);
                });
                document.querySelectorAll('.cl-line-account').forEach(s =>
                    lineChoices.push(new Choices(s, choicesOpts('Hereda de la principal'))));

                App.modal.show('classify-modal');
            } catch (e) {
                App.toast.error('No se pudo cargar la clasificación.');
            }
        }

        // When the master changes, fill every line that has NO explicit override
        // so the "one account for all" case is one click. Lines already overridden
        // keep their value.
        document.getElementById('cl-abono')?.addEventListener('change', () => {
            const master = masterChoices?.getValue(true);
            if (!master) return;
            document.querySelectorAll('.cl-line-account').forEach((s, i) => {
                if (!s.value) {
                    lineChoices[i]?.setChoiceByValue(String(master));
                }
            });
        });

        function readMaster() {
            return masterChoices ? masterChoices.getValue(true) : document.getElementById('cl-abono').value;
        }

        function readLineAccounts() {
            const map = {};
            document.querySelectorAll('.cl-line-account').forEach(s => {
                if (s.value) map[s.dataset.index] = s.value;
            });
            return map;
        }

        function readLineNoDeducible() {
            const map = {};
            document.querySelectorAll('.cl-line-noded').forEach(inp => {
                if (inp.value !== '') map[inp.dataset.index] = inp.value;
            });
            return map;
        }

        const submit = document.getElementById('cl-submit');
        submit?.addEventListener('click', async () => {
            const master = readMaster();
            if (!master) {
                App.toast.warning('Selecciona la cuenta de abono.');
                return;
            }

            await App.loading.button(submit, async () => {
                try {
                    const res = await App.http.post(`${base}/${currentId}/classify`, {
                        cuenta_abono_id: master,
                        line_accounts: readLineAccounts(),
                        line_no_deducible: readLineNoDeducible(),
                    });
                    App.toast.success(res.message);
                    App.modal.hide('classify-modal');
                    setTimeout(() => window.location.reload(), 900);
                } catch (e) {
                    App.toast.error(e.message || 'No se pudo confirmar.');
                }
            });
        });

        document.querySelectorAll('[data-classify]').forEach(btn =>
            btn.addEventListener('click', () => openModal(btn.dataset.classify)));
    })();
</script>
@endpush