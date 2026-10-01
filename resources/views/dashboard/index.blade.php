@extends('layouts.app')
@section('title', 'Panel · ContaSAT')

@section('content')
@php
    // Short header labels (full label stays in the cell tooltip). Order follows
    // ActivityStatus::ACTIVITIES, which is the order the rows arrive in.
    $shortLabels = [
        'op_32d'               => '32D',
        'constancia'           => 'Constancia',
        'descarga_xml'         => 'Descarga XML',
        'clasificacion_xml'    => 'Clasif. XML',
        'edo_cuenta_solicitud' => 'Solicitud',
        'conciliacion'         => 'Conciliación',
        'presentacion_dyp'     => 'DYP',
        'pago_declaracion'     => 'Pago decl.',
        'diot'                 => 'DIOT',
        'econtabilidad'        => 'e.contab.',
        'expediente_fiscal'    => 'Expediente',
    ];
    $statusLabels = [
        'realizada'  => 'Realizada',
        'en_proceso' => 'En proceso',
        'pendiente'  => 'Pendiente',
        'no_aplica'  => 'No aplica',
    ];
    // Header columns from the canonical activity list, so the header always has
    // exactly the 11 columns every row emits (rows carry all 11 even period-less).
    $activityOrder = collect(\App\Models\ActivityStatus::ACTIVITIES)
        ->map(fn($meta, $key) => ['key' => $key, 'label' => $meta['label']])
        ->values();
@endphp
<style>
    .dash-sem { border-collapse:separate; border-spacing:0; }
    .dash-sem th.act { padding:.4rem .1rem; vertical-align:bottom; text-align:center; }
    .dash-sem th.act span {
        writing-mode:vertical-rl; transform:rotate(180deg);
        display:inline-block; font-size:10.5px; font-weight:600;
        text-transform:none; letter-spacing:0; color:var(--text-muted);
        white-space:nowrap; line-height:1;
    }
    .dash-sem td.act { text-align:center; padding-left:.1rem; padding-right:.1rem; }
    .cal-dot { display:inline-block; width:12px; height:12px; border-radius:50%; background:var(--border); }
    .cal-dot--realizada  { background:var(--ok); }
    .cal-dot--en_proceso { background:var(--warn); }
    .cal-dot--pendiente  { background:var(--danger); }
    .cal-dot--no_aplica  { background:var(--border); opacity:.6; }
    .dash-prog { display:flex; align-items:center; gap:.5rem; }
    .dash-prog small { color:var(--text-muted); font-size:11px; white-space:nowrap; font-variant-numeric:tabular-nums; }
    .dash-legend { display:flex; gap:1rem; flex-wrap:wrap; font-size:12px; color:var(--text-muted); }
    .dash-legend span { display:inline-flex; align-items:center; gap:.35rem; }
</style>

<div class="page-head" data-reveal>
    <div>
        <h1>Panel de trabajo</h1>
        <div class="subtitle">Estado de todos los clientes para el periodo seleccionado</div>
    </div>

    {{-- Month/year selector — drives the whole board for the selected period. --}}
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

{{-- Client activity board --}}
<div class="card-clean" data-reveal>
    <div class="card-clean__head d-flex align-items-center justify-content-between flex-wrap gap-2">
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
        <div class="dash-legend px-3 pt-2 pb-1">
            <span><span class="cal-dot cal-dot--realizada"></span> Realizada</span>
            <span><span class="cal-dot cal-dot--en_proceso"></span> En proceso</span>
            <span><span class="cal-dot cal-dot--pendiente"></span> Pendiente</span>
            <span><span class="cal-dot cal-dot--no_aplica"></span> No aplica</span>
        </div>
        <div class="table-responsive">
            <table class="table-clean dash-sem">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>RFC</th>
                        @foreach($activityOrder as $act)
                            <th class="act"><span>{{ $shortLabels[$act['key']] ?? $act['label'] }}</span></th>
                        @endforeach
                        <th style="width:150px;">Progreso</th>
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
                            @foreach($row['activities'] as $act)
                                <td class="act">
                                    <span class="cal-dot cal-dot--{{ $act['status'] }}"
                                          title="{{ $act['label'] }}: {{ $statusLabels[$act['status']] ?? $act['status'] }}"></span>
                                </td>
                            @endforeach
                            <td>
                                <div class="dash-prog">
                                    <div class="rail"><div class="rail__fill" style="width: {{ $row['progress'] }}%;"></div></div>
                                    <small>{{ $row['activities_done'] }}/{{ $row['activities_total'] }}</small>
                                </div>
                            </td>
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

        {{-- Agregar cliente — sits below the last client in the list. --}}
        <div class="p-3">
            <a href="{{ route('clients.create') }}" class="btn btn-brand btn-icon">
                <i class="fa-solid fa-plus"></i> Agregar cliente
            </a>
        </div>
    @endif
</div>
@endsection
