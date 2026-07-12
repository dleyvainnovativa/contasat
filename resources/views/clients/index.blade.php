@extends('layouts.app')
@section('title', 'Clientes · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Clientes</h1>
        <div class="subtitle">Administra los contribuyentes que procesas</div>
    </div>
    <a href="{{ route('clients.create') }}" class="btn btn-brand btn-icon">
        <i class="fa-solid fa-plus"></i> Nuevo cliente
    </a>
</div>

<div class="card-clean" data-reveal>
    <div class="card-clean__head">
        <form method="GET" action="{{ route('clients.index') }}" class="d-flex align-items-center gap-2 w-100">
            <div class="position-relative flex-grow-1" style="max-width:360px;">
                <i class="fa-solid fa-magnifying-glass position-absolute text-muted" style="left:.75rem; top:50%; transform:translateY(-50%); font-size:13px;"></i>
                <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por nombre o RFC" style="padding-left:2.25rem;">
            </div>
            <select name="estado" class="form-select" style="width:auto;" onchange="this.form.submit()">
                <option value="">Todos</option>
                <option value="activos" @selected($estado === 'activos')>Activos</option>
                <option value="inactivos" @selected($estado === 'inactivos')>Inactivos</option>
            </select>
        </form>
    </div>

    @if($clients->isEmpty())
        <div class="empty-state">
            <i class="fa-solid fa-user-plus"></i>
            <h3>Sin resultados</h3>
            <p>No hay clientes que coincidan con tu búsqueda.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>RFC</th>
                        <th>Régimen</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clients as $client)
                        <tr>
                            <td>
                                <a href="{{ route('clients.show', $client) }}" style="font-weight:550; color:var(--text);">
                                    {{ $client->display_name }}
                                </a>
                                @if($client->nombre_comercial)
                                    <div class="text-muted" style="font-size:12px;">{{ $client->razon_social }}</div>
                                @endif
                            </td>
                            <td><span class="data text-muted">{{ $client->rfc }}</span></td>
                            <td><span style="font-size:13px;">{{ $client->regimen_fiscal === 'moral' ? 'Persona moral' : 'Persona física' }}</span></td>
                            <td>
                                @if($client->activo)
                                    <span class="badge-status s-success"><i class="fa-solid fa-circle-check"></i> Activo</span>
                                @else
                                    <span class="badge-status s-secondary"><i class="fa-solid fa-circle-minus"></i> Inactivo</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('clients.activate', $client) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-soft" style="padding:.35rem .65rem; font-size:12.5px;" title="Activar como cliente de trabajo">
                                        <i class="fa-solid fa-arrow-pointer"></i>
                                    </button>
                                </form>
                                <a href="{{ route('clients.edit', $client) }}" class="btn btn-soft" style="padding:.35rem .65rem; font-size:12.5px;">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="mt-3">{{ $clients->links() }}</div>
@endsection
