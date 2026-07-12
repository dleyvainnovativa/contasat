@extends('layouts.app')
@section('title', $client->display_name . ' · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>{{ $client->display_name }}</h1>
        <div class="subtitle data">{{ $client->rfc }} · {{ $client->regimen_fiscal === 'moral' ? 'Persona moral' : 'Persona física' }}</div>
    </div>
    <div class="d-flex gap-2">
        <form method="POST" action="{{ route('clients.activate', $client) }}">
            @csrf
            <button class="btn btn-soft btn-icon"><i class="fa-solid fa-arrow-pointer"></i> Activar</button>
        </form>
        <a href="{{ route('clients.edit', $client) }}" class="btn btn-brand btn-icon"><i class="fa-solid fa-pen"></i> Editar</a>
    </div>
</div>

<div class="row g-3">
    {{-- Detail card --}}
    <div class="col-lg-4" data-reveal>
        <div class="card-clean">
            <div class="card-clean__head"><strong>Datos fiscales</strong></div>
            <div class="card-clean__body">
                <dl class="mb-0" style="display:grid; grid-template-columns:auto 1fr; gap:.6rem 1rem; font-size:13.5px;">
                    <dt class="text-muted">RFC</dt><dd class="mb-0 data">{{ $client->rfc }}</dd>
                    <dt class="text-muted">Razón social</dt><dd class="mb-0">{{ $client->razon_social }}</dd>
                    <dt class="text-muted">C.P.</dt><dd class="mb-0 data">{{ $client->codigo_postal ?: '—' }}</dd>
                    <dt class="text-muted">Email</dt><dd class="mb-0">{{ $client->email ?: '—' }}</dd>
                    <dt class="text-muted">Teléfono</dt><dd class="mb-0">{{ $client->telefono ?: '—' }}</dd>
                </dl>
                @if($client->notas)
                    <hr style="border-color:var(--border)">
                    <div class="text-muted" style="font-size:12px;">Notas</div>
                    <p class="mb-0" style="font-size:13.5px;">{{ $client->notas }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Periods --}}
    <div class="col-lg-8" data-reveal data-reveal-delay="60">
        <div class="card-clean">
            <div class="card-clean__head">
                <strong>Periodos</strong>
                <form method="POST" action="{{ route('periods.open', $client) }}" class="d-flex align-items-center gap-2">
                    @csrf
                    <select name="month" class="form-select" style="width:auto; padding:.35rem .65rem; font-size:13px;">
                        @foreach(['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'] as $i => $m)
                            <option value="{{ $i+1 }}" @selected(now()->month === $i+1)>{{ $m }}</option>
                        @endforeach
                    </select>
                    <select name="year" class="form-select" style="width:auto; padding:.35rem .65rem; font-size:13px;">
                        @for($y = now()->year; $y >= now()->year - 3; $y--)
                            <option value="{{ $y }}">{{ $y }}</option>
                        @endfor
                    </select>
                    <button class="btn btn-brand btn-icon" style="padding:.4rem .7rem; font-size:13px;"><i class="fa-solid fa-plus"></i> Abrir</button>
                </form>
            </div>

            @if($client->periods->isEmpty())
                <div class="empty-state">
                    <i class="fa-solid fa-calendar-plus"></i>
                    <h3>Sin periodos</h3>
                    <p>Abre un periodo para empezar a procesar facturas y estados de cuenta.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table-clean">
                        <thead><tr><th>Periodo</th><th>Estado</th><th style="width:140px;">Progreso</th><th></th></tr></thead>
                        <tbody>
                            @foreach($client->periods as $period)
                                <tr>
                                    <td style="font-weight:550;">{{ $period->label }}</td>
                                    <td>
                                        <span class="badge-status s-{{ $period->status->color() }}">
                                            <i class="fa-solid fa-{{ $period->status->icon() }}"></i> {{ $period->status->label() }}
                                        </span>
                                    </td>
                                    <td><div class="rail"><div class="rail__fill" style="width:{{ $period->progress }}%;"></div></div></td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('periods.activate', $period) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-soft" style="padding:.35rem .65rem; font-size:12.5px;"><i class="fa-solid fa-arrow-right-to-bracket"></i> Abrir</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
