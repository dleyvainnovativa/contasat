{{-- Request a period's CFDIs from SAT. The period is a calendar month; the system
     refuses to resubmit a period it has already requested, because SAT permanently
     exhausts a period after two requests. --}}
<div class="modal fade" id="download-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:var(--radius-lg); border-color:var(--border); background:var(--surface);">
            <div class="modal-body p-4">
                <h5 class="mb-1" style="font-weight:600;">Solicitar descarga</h5>
                <p class="text-muted mb-3" style="font-size:13px;">
                    Cliente: <strong id="dl-client-name"></strong>
                </p>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Mes</label>
                        <select id="dl-month" class="form-select">
                            @foreach(['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $i => $m)
                                <option value="{{ $i+1 }}" @selected(now()->subMonth()->month === $i+1)>{{ $m }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Año</label>
                        <select id="dl-year" class="form-select">
                            {{-- SAT allows six years back, current included. --}}
                            @for($y = now()->year; $y >= now()->year - 5; $y--)
                                <option value="{{ $y }}" @selected(now()->subMonth()->year === $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label">Dirección</label>
                        <select id="dl-direction" class="form-select">
                            <option value="received">Recibidas</option>
                            <option value="issued">Emitidas</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Contenido</label>
                        <select id="dl-kind" class="form-select">
                            <option value="xml">XML (facturas)</option>
                            <option value="metadata">Metadata (solo listado)</option>
                        </select>
                    </div>
                </div>

                <div class="form-hint" style="color:var(--warn);">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    Un periodo solo se puede solicitar <strong>dos veces en la vida</strong>.
                    Usa <em>Metadata</em> para probar: no consume ese límite.
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand btn-icon" id="dl-submit"><i class="fa-solid fa-cloud-arrow-down"></i> Solicitar</button>
                </div>
            </div>
        </div>
    </div>
</div>
