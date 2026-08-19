{{-- Create-account modal. Context-aware: on the client accounts page it creates a
     client account; on the global catalog page, a global one (the store route
     differs — pass $storeRoute from the including view). --}}
<div class="modal fade" id="create-account-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg); border-color:var(--border); background:var(--surface);">
            <div class="modal-body p-4">
                <h5 class="mb-3" style="font-weight:600;">Crear cuenta</h5>

                <div class="row g-2">
                    <div class="col-6 mb-2">
                        <label class="form-label">Número de cuenta</label>
                        <input type="text" id="ca-numero" class="form-control" placeholder="105.01.5">
                    </div>
                    <div class="col-6 mb-2">
                        <label class="form-label">Código agrupador</label>
                        <input type="text" id="ca-agrupador" class="form-control" placeholder="105.01">
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label">Nombre</label>
                    <input type="text" id="ca-nombre" class="form-control" placeholder="Nombre de la cuenta">
                </div>

                <div class="row g-2">
                    <div class="col-4 mb-2">
                        <label class="form-label">Naturaleza</label>
                        <select id="ca-naturaleza" class="form-select">
                            <option value="D">Deudora</option>
                            <option value="A">Acreedora</option>
                        </select>
                    </div>
                    <div class="col-4 mb-2">
                        <label class="form-label">Nivel</label>
                        <input type="number" id="ca-nivel" class="form-control" value="2" min="1" max="6">
                    </div>
                    <div class="col-4 mb-2">
                        <label class="form-label">Afectable</label>
                        <select id="ca-afectable" class="form-select">
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label">Cuenta padre (opcional)</label>
                    <select id="ca-parent">
                        <option value="">Sin padre</option>
                        @foreach(($parentOptions ?? []) as $p)
                            <option value="{{ $p->id }}">{{ $p->numero_cuenta }} — {{ $p->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand btn-icon" id="ca-submit"><i class="fa-solid fa-check"></i> Crear</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script type="module">

(function () {
    const parentSel = document.getElementById('ca-parent');
    if (parentSel) {
        new Choices(parentSel, {
            searchEnabled: true, itemSelectText: '', shouldSort: false,
            searchPlaceholderValue: 'Buscar cuenta padre…',
        });
    }

    document.querySelectorAll('[data-create-account]').forEach(b =>
        b.addEventListener('click', () => App.modal.show('create-account-modal')));

    const submit = document.getElementById('ca-submit');
    submit?.addEventListener('click', async function () {
        const body = {
            numero_cuenta:    document.getElementById('ca-numero').value.trim(),
            codigo_agrupador: document.getElementById('ca-agrupador').value.trim(),
            nombre:           document.getElementById('ca-nombre').value.trim(),
            naturaleza:       document.getElementById('ca-naturaleza').value,
            nivel:            document.getElementById('ca-nivel').value,
            es_afectable:     document.getElementById('ca-afectable').value === '1',
            parent_id:        parentSel.value || null,
        };
        if (!body.numero_cuenta || !body.nombre || !body.codigo_agrupador) {
            App.toast.warning('Número, nombre y agrupador son obligatorios.'); return;
        }
        await App.loading.button(this, async () => {
            try {
                const res = await App.http.post('{{ $storeRoute ?? route('accounts.store') }}', body);
                App.toast.success(res.message);
                App.modal.hide('create-account-modal');
                setTimeout(() => window.location.reload(), 900);
            } catch (e) { App.toast.error(e.message); }
        });
    });
})();
</script>
@endpush
