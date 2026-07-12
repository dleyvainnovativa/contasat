@extends('layouts.app')
@section('title', 'Nuevo cliente · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Nuevo cliente</h1>
        <div class="subtitle">Registra un contribuyente</div>
    </div>
    <a href="{{ route('clients.index') }}" class="btn btn-soft btn-icon"><i class="fa-solid fa-arrow-left"></i> Volver</a>
</div>

<div class="card-clean" data-reveal style="max-width:820px;">
    <div class="card-clean__body">
        <form method="POST" action="{{ route('clients.store') }}">
            @include('clients._form')
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('clients.index') }}" class="btn btn-soft">Cancelar</a>
                <button type="submit" class="btn btn-brand btn-icon"><i class="fa-solid fa-check"></i> Crear cliente</button>
            </div>
        </form>
    </div>
</div>
@endsection
