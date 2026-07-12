@extends('layouts.app')
@section('title', 'Editar cliente · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Editar cliente</h1>
        <div class="subtitle data">{{ $client->rfc }}</div>
    </div>
    <a href="{{ route('clients.show', $client) }}" class="btn btn-soft btn-icon"><i class="fa-solid fa-arrow-left"></i> Volver</a>
</div>

<div class="card-clean" data-reveal style="max-width:820px;">
    <div class="card-clean__body">
        <form method="POST" action="{{ route('clients.update', $client) }}">
            @method('PUT')
            @include('clients._form')
            <div class="d-flex justify-content-between mt-4">
                <button type="button" class="btn btn-soft" style="color:var(--danger)"
                    onclick="App.modal.confirm({title:'Archivar cliente', message:'El cliente se archivará y dejará de aparecer en el panel. ¿Continuar?', confirmText:'Archivar', danger:true}).then(ok => { if(ok) document.getElementById('delete-form').submit(); })">
                    <i class="fa-solid fa-box-archive"></i> Archivar
                </button>
                <div class="d-flex gap-2">
                    <a href="{{ route('clients.show', $client) }}" class="btn btn-soft">Cancelar</a>
                    <button type="submit" class="btn btn-brand btn-icon"><i class="fa-solid fa-check"></i> Guardar cambios</button>
                </div>
            </div>
        </form>
        <form id="delete-form" method="POST" action="{{ route('clients.destroy', $client) }}" class="d-none">
            @csrf @method('DELETE')
        </form>
    </div>
</div>
@endsection
