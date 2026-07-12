{{-- Shared client form fields. Included by create + edit. --}}
@csrf
<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label">Razón social <span style="color:var(--danger)">*</span></label>
        <input type="text" name="razon_social" class="form-control" value="{{ old('razon_social', $client->razon_social) }}" required>
    </div>
    <div class="col-md-4">
        <label class="form-label">RFC <span style="color:var(--danger)">*</span></label>
        <input type="text" name="rfc" class="form-control data text-uppercase" value="{{ old('rfc', $client->rfc) }}" maxlength="13" required>
    </div>

    <div class="col-md-8">
        <label class="form-label">Nombre comercial</label>
        <input type="text" name="nombre_comercial" class="form-control" value="{{ old('nombre_comercial', $client->nombre_comercial) }}">
        <div class="form-hint">Opcional. Se muestra en lugar de la razón social cuando existe.</div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Régimen</label>
        <select name="regimen_fiscal" class="form-select">
            <option value="moral" @selected(old('regimen_fiscal', $client->regimen_fiscal) === 'moral')>Persona moral</option>
            <option value="fisica" @selected(old('regimen_fiscal', $client->regimen_fiscal) === 'fisica')>Persona física</option>
        </select>
    </div>

    <div class="col-md-4">
        <label class="form-label">Código postal</label>
        <input type="text" name="codigo_postal" class="form-control data" value="{{ old('codigo_postal', $client->codigo_postal) }}" maxlength="5">
    </div>
    <div class="col-md-4">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="{{ old('email', $client->email) }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Teléfono</label>
        <input type="text" name="telefono" class="form-control" value="{{ old('telefono', $client->telefono) }}">
    </div>

    <div class="col-12">
        <label class="form-label">Notas</label>
        <textarea name="notas" class="form-control" rows="3">{{ old('notas', $client->notas) }}</textarea>
    </div>

    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" class="form-check-input" name="activo" id="activo" value="1" @checked(old('activo', $client->activo ?? true))>
            <label class="form-check-label" for="activo">Cliente activo</label>
        </div>
    </div>
</div>
