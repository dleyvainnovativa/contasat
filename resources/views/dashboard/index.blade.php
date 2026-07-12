@extends('layouts.app')
@section('title', 'Panel · ContaSAT')

@section('content')
<div class="page-head" data-reveal>
    <div>
        <h1>Panel de trabajo</h1>
        <div class="subtitle">Estado de todos los clientes para el periodo seleccionado</div>
    </div>

    {{-- Month/year selector --}}
    <form method="GET" action="{{ route('dashboard') }}" class="d-flex align-items-center gap-2">
        <select name="month" class="form-select" style="width:auto;" onchange="this.form.submit()">
            @foreach(['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'] as $i => $name)
                <option value="{{ $i + 1 }}" @selected($month === $i + 1)>{{ $name }}</option>
            @endforeach
        </select>
        <select name="year" class="form-select" style="width:auto;" onchange="this.form.submit()">
            @for($y = now()->year; $y >= now()->year - 4; $y--)
                <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
            @endfor
        </select>
    </form>
</div>

{{-- Status summary cards --}}
<div class="row g-3 mb-4">
    @foreach($statuses as $status)
        <div class="col-6 col-md-4 col-xl-2" data-reveal data-reveal-delay="{{ $loop->index * 40 }}">
            <div class="stat-card">
                <span class="stat-card__label">
                    <i class="fa-solid fa-{{ $status->icon() }}" style="color: var(--{{ $status->color() === 'secondary' ? 'neutral' : ($status->color() === 'primary' ? 'brand-500' : $status->color()) }});"></i>
                    {{ $status->label() }}
                </span>
                <span class="stat-card__value">{{ $totals[$status->value] ?? 0 }}</span>
            </div>
        </div>
    @endforeach
</div>

{{-- Client status table --}}
<div class="card-clean" data-reveal>
    <div class="card-clean__head">
        <strong>{{ $monthLabel }}</strong>
        <span class="text-muted" style="font-size:13px;">{{ $overview->count() }} clientes activos</span>
    </div>

    @if($overview->isEmpty())
        <div class="empty-state">
            <i class="fa-solid fa-users-slash"></i>
            <h3>Sin clientes activos</h3>
            <p>Agrega tu primer cliente para empezar a procesar periodos.</p>
            <a href="{{ route('clients.create') }}" class="btn btn-brand btn-icon mt-2">
                <i class="fa-solid fa-plus"></i> Nuevo cliente
            </a>
        </div>
    @else
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>RFC</th>
                        <th>Estado</th>
                        <th style="width:160px;">Progreso</th>
                        <th class="text-end">Facturas</th>
                        <th class="text-end">Movimientos</th>
                        <th class="text-end">Conciliados</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($overview as $row)
                        <tr>
                            <td>
                                <a href="{{ route('clients.show', $row['client']) }}" style="font-weight:550; color:var(--text);">
                                    {{ $row['client']->display_name }}
                                </a>
                            </td>
                            <td><span class="data text-muted">{{ $row['client']->rfc }}</span></td>
                            <td>
                                <span class="badge-status s-{{ $row['status']->color() }}">
                                    <i class="fa-solid fa-{{ $row['status']->icon() }}"></i>
                                    {{ $row['status']->label() }}
                                </span>
                            </td>
                            <td>
                                <div class="rail"><div class="rail__fill" style="width: {{ $row['progress'] }}%;"></div></div>
                            </td>
                            <td class="text-end data">{{ $row['invoices'] }}</td>
                            <td class="text-end data">{{ $row['movements'] }}</td>
                            <td class="text-end data">{{ $row['matched'] }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('periods.open', $row['client']) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="year" value="{{ $year }}">
                                    <input type="hidden" name="month" value="{{ $month }}">
                                    <button class="btn btn-soft btn-icon" style="padding:.35rem .65rem; font-size:12.5px;">
                                        <i class="fa-solid fa-arrow-right-to-bracket"></i> Abrir
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
