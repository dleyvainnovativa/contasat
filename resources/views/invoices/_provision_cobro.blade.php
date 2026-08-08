{{-- The two accounting cuadros from the client's spec, for income invoices.
     Left: provisión (accrual). Right: cobro (cash). Matches the drawn layout.
     Include from invoices/show.blade.php when the invoice is emitida + tipo I. --}}

@php
    $provision = \App\Models\Poliza::where('invoice_id', $invoice->id)->where('tipo', 'provision')->with('lines')->first();
    $cobro = \App\Models\Poliza::where('invoice_id', $invoice->id)->where('tipo', 'cobro')->with('lines')->first();

    // Revenue accounts (4xx afectable) for the per-concepto pickers.
    $revenueAccounts = \App\Models\Account::forClient($invoice->client_id)
        ->where('es_afectable', true)->where('activo', true)
        ->where('codigo_agrupador', 'like', '4%')
        ->orderBy('numero_cuenta')->get(['id', 'numero_cuenta', 'nombre']);

    // Is a statement loaded for this period? Gates the "estado de cuenta" option.
    $hasStatement = \App\Models\BankStatement::where('period_id', $invoice->period_id)->exists();
@endphp

<div class="row g-3 mt-1" data-reveal>
    {{-- CUADRO DE PROVISIÓN --}}
    <div class="col-lg-6">
        <div class="card-clean" style="height:100%;">
            <div class="card-clean__head">
                <strong>Asiento de provisión</strong>
                @if($provision)
                    <span class="badge-status {{ $provision->cuadra ? 's-success' : 's-danger' }}" style="font-size:11px;">
                        <i class="fa-solid fa-check"></i> Generada
                    </span>
                @endif
            </div>
            <div class="card-clean__body">
                @if($provision)
                    <table class="table-clean" style="margin:-.5rem 0;">
                        <tbody>
                            @foreach($provision->lines as $l)
                                <tr>
                                    <td class="data" style="font-size:12px;">{{ $l->numero_cuenta }}</td>
                                    <td style="font-size:11.5px;" class="text-muted">{{ \Illuminate\Support\Str::limit($l->nombre_cuenta, 22) }}</td>
                                    <td class="text-end data">{{ $l->cargo > 0 ? number_format($l->cargo, 2) : '' }}</td>
                                    <td class="text-end data">{{ $l->abono > 0 ? number_format($l->abono, 2) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted" style="font-size:12.5px;">Selecciona la cuenta de ingreso por concepto y genera la póliza de provisión.</p>
                    <div id="concept-rows">
                        @foreach($invoice->lines as $i => $line)
                            <div class="mb-2">
                                <div style="font-size:12px; margin-bottom:.25rem;">{{ \Illuminate\Support\Str::limit($line->descripcion, 40) }} · <span class="data">${{ number_format($line->importe, 2) }}</span></div>
                                <select class="form-select concept-account" data-index="{{ $i }}" style="font-size:12.5px;">
                                    <option value="">Cuenta de ingreso…</option>
                                    @foreach($revenueAccounts as $acc)
                                        <option value="{{ $acc->id }}" @selected($invoice->cuenta_abono_id === $acc->id)>{{ $acc->numero_cuenta }} — {{ $acc->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                    <button class="btn btn-brand btn-icon w-100 justify-content-center mt-2" id="gen-provision">
                        <i class="fa-solid fa-file-circle-plus"></i> Generar póliza de provisión
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- CUADRO DE COBRO --}}
    <div class="col-lg-6">
        <div class="card-clean" style="height:100%;">
            <div class="card-clean__head">
                <strong>Asiento de cobro</strong>
                @if($cobro)
                    <span class="badge-status {{ $cobro->cuadra ? 's-success' : 's-danger' }}" style="font-size:11px;">
                        <i class="fa-solid fa-check"></i> Generada
                    </span>
                @endif
            </div>
            <div class="card-clean__body">
                @if($cobro)
                    <table class="table-clean" style="margin:-.5rem 0;">
                        <tbody>
                            @foreach($cobro->lines as $l)
                                <tr>
                                    <td class="data" style="font-size:12px;">{{ $l->numero_cuenta }}</td>
                                    <td style="font-size:11.5px;" class="text-muted">{{ \Illuminate\Support\Str::limit($l->nombre_cuenta, 22) }}</td>
                                    <td class="text-end data">{{ $l->cargo > 0 ? number_format($l->cargo, 2) : '' }}</td>
                                    <td class="text-end data">{{ $l->abono > 0 ? number_format($l->abono, 2) : '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="text-muted" style="font-size:11.5px; margin-top:.5rem;">
                        Fecha: {{ $cobro->fecha->format('d/m/Y') }} · Origen: {{ $cobro->origen_pago }}
                    </div>
                @elseif(! $provision)
                    <p class="text-muted" style="font-size:12.5px;">
                        <i class="fa-solid fa-lock"></i> Genera primero la póliza de provisión.
                    </p>
                @else
                    <p class="text-muted" style="font-size:12.5px;">Establece la fecha de pago para generar la póliza de cobro.</p>

                    {{-- Three payment sources, matching the spec --}}
                    <div class="mb-2">
                        <label class="form-label" style="font-size:12px;">Opción 1 — Ingresar fecha</label>
                        <input type="date" id="cobro-fecha" class="form-control" style="font-size:13px;" value="{{ now()->format('Y-m-d') }}">
                    </div>

                    <div class="mb-2" style="font-size:12px; color:var(--text-muted);">
                        <label class="d-flex align-items-center gap-2" style="{{ $invoice->uuid ? '' : 'opacity:.5;' }}">
                            <input type="radio" name="cobro-origen" value="complemento" {{ $invoice->uuid ? '' : 'disabled' }}>
                            Opción 2 — Complemento de pago (coincidencia)
                        </label>
                        <label class="d-flex align-items-center gap-2 mt-1" style="{{ $hasStatement ? '' : 'opacity:.5;' }}">
                            <input type="radio" name="cobro-origen" value="estado_cuenta" {{ $hasStatement ? '' : 'disabled' }}>
                            Opción 3 — Estado de cuenta {{ $hasStatement ? '' : '(no cargado)' }}
                        </label>
                        <label class="d-flex align-items-center gap-2 mt-1">
                            <input type="radio" name="cobro-origen" value="manual" checked>
                            Manual (fecha ingresada)
                        </label>
                    </div>

                    <div id="cobro-coincidencia" style="font-size:12px; margin:.35rem 0;"></div>

                    <button class="btn btn-brand btn-icon w-100 justify-content-center mt-2" id="gen-cobro">
                        <i class="fa-solid fa-file-circle-plus"></i> Generar póliza de cobro
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script type="module">

(function () {
    const invoiceId = {{ $invoice->id }};

    // Enhance each per-concepto revenue select. They're already in the DOM (not in
    // a modal), so a single init pass is enough — no destroy/rebuild needed.
    const conceptChoices = [];
    document.querySelectorAll('.concept-account').forEach(sel => {
        conceptChoices.push(new Choices(sel, {
            searchEnabled: true,
            searchResultLimit: 20,
            shouldSort: false,               // keep numero_cuenta order from the server
            itemSelectText: '',
            searchPlaceholderValue: 'Buscar cuenta…',
            noResultsText: 'Sin coincidencias',
            noChoicesText: 'No hay cuentas disponibles',
            placeholderValue: 'Cuenta de ingreso…',
        }));
    });

    document.getElementById('gen-provision')?.addEventListener('click', async function () {
        const accounts = {};
        document.querySelectorAll('.concept-account').forEach(sel => {
            // Read the value straight off the underlying <select> — Choices keeps it
            // in sync, so sel.value is reliable here.
            if (sel.value) accounts[sel.dataset.index] = sel.value;
        });
        await App.loading.button(this, async () => {
            try {
                const res = await App.http.post(`{{ url('invoices') }}/${invoiceId}/provision`, { concept_accounts: accounts });
                App.toast.success(res.message);
                setTimeout(() => window.location.reload(), 900);
            } catch (e) { App.toast.error(e.message); }
        });
    });

    // When the user picks complemento or estado_cuenta, fetch the matching payment
    // and show its date — the "coincidencia" from the spec.
    const coincidencia = document.getElementById('cobro-coincidencia');
    document.querySelectorAll('input[name="cobro-origen"]').forEach(radio => {
        radio.addEventListener('change', async () => {
            const origen = radio.value;
            if (origen === 'manual') {
                coincidencia.innerHTML = '';
                document.getElementById('cobro-fecha').disabled = false;
                return;
            }
            try {
                const res = await App.http.get(`{{ url('invoices') }}/${invoiceId}/cobro-candidates?origen=${origen}`);
                if (res.found) {
                    const c = res.candidates[0];
                    coincidencia.innerHTML = `<span style="color:var(--ok);"><i class="fa-solid fa-check"></i> Coincidencia: ${c.fecha} · $${Number(c.monto).toLocaleString('es-MX',{minimumFractionDigits:2})}</span>`;
                    // The source's date is authoritative; reflect it and lock manual entry.
                    document.getElementById('cobro-fecha').value = c.fecha;
                    document.getElementById('cobro-fecha').disabled = true;
                } else {
                    coincidencia.innerHTML = `<span style="color:var(--warn);"><i class="fa-solid fa-triangle-exclamation"></i> Sin coincidencia para esta opción.</span>`;
                    document.getElementById('cobro-fecha').disabled = false;
                }
            } catch (e) {
                coincidencia.innerHTML = '';
            }
        });
    });

    document.getElementById('gen-cobro')?.addEventListener('click', async function () {
        const origen = document.querySelector('input[name="cobro-origen"]:checked')?.value || 'manual';
        const body = { origen };
        if (origen === 'manual') {
            const fecha = document.getElementById('cobro-fecha').value;
            if (!fecha) { App.toast.warning('Ingresa la fecha de pago.'); return; }
            body.fecha_pago = fecha;
        }
        await App.loading.button(this, async () => {
            try {
                const res = await App.http.post(`{{ url('invoices') }}/${invoiceId}/cobro`, body);
                App.toast.success(res.message);
                setTimeout(() => window.location.reload(), 900);
            } catch (e) { App.toast.error(e.message); }
        });
    });
})();
</script>
@endpush
