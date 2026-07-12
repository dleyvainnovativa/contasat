{{-- Suggested and confirmed match pairs: movement <-> invoice, side by side. --}}
<div class="card-clean">
    @if($matches->isEmpty())
        <div class="empty-state">
            <i class="fa-solid fa-link-slash"></i>
            <h3>Sin enlaces</h3>
            <p>Ejecuta la conciliación para generar enlaces automáticos.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="table-clean">
                <thead>
                    <tr>
                        <th>Movimiento (banco)</th>
                        <th>Factura (CFDI)</th>
                        <th class="text-center">Confianza</th>
                        <th>Cuenta contable</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($matches as $match)
                        @php $mov = $match->movement; $inv = $match->invoice; @endphp
                        <tr data-match-row="{{ $match->id }}" data-state="{{ $match->estado }}">
                            {{-- Movement --}}
                            <td>
                                <div class="data" style="font-size:12px; color:var(--text-muted);">{{ $mov?->fecha?->format('d/m/Y') }}</div>
                                <div style="font-size:13px;" class="text-truncate" title="{{ $mov?->descripcion }}">{{ \Illuminate\Support\Str::limit($mov?->descripcion, 40) }}</div>
                                <div class="data" style="font-weight:600; font-size:13.5px; color:{{ $mov?->cargo > 0 ? 'var(--warn)' : 'var(--ok)' }};">
                                    ${{ number_format($mov?->cargo > 0 ? $mov->cargo : $mov->deposito, 2) }}
                                </div>
                            </td>
                            {{-- Invoice --}}
                            <td>
                                <div class="data" style="font-size:12px; color:var(--text-muted);">{{ $inv?->fecha_emision?->format('d/m/Y') }} · {{ $inv?->serie }}{{ $inv?->folio }}</div>
                                <div style="font-size:13px;" class="text-truncate">
                                    {{ $inv?->tipo === 'emitida' ? ($inv?->receptor_nombre ?: $inv?->receptor_rfc) : ($inv?->emisor_nombre ?: $inv?->emisor_rfc) }}
                                </div>
                                <div class="data" style="font-weight:600; font-size:13.5px;">${{ number_format($inv?->total, 2) }}</div>
                            </td>
                            {{-- Score --}}
                            <td class="text-center">
                                @php $pct = (int) round(($match->score ?? 0) * 100); @endphp
                                <span class="badge-status s-{{ $pct >= 85 ? 'success' : ($pct >= 65 ? 'warning' : 'secondary') }}">
                                    {{ $pct }}%
                                </span>
                            </td>
                            {{-- Account (only meaningful once confirmed) --}}
                            <td>
                                @if($match->estado === 'confirmado')
                                    <select class="form-select" style="font-size:12.5px; padding:.3rem .5rem; min-width:180px;" data-account-select="{{ $mov?->id }}">
                                        <option value="">— Asignar cuenta —</option>
                                        @foreach($accounts as $acc)
                                            <option value="{{ $acc->id }}"
                                                @selected($mov?->account_id === $acc->id || (($accountSuggestions[$mov?->id] ?? null) === $acc->id && !$mov?->account_id))>
                                                {{ $acc->numero_cuenta }} — {{ $acc->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="text-muted" style="font-size:12px;">Confirma primero</span>
                                @endif
                            </td>
                            {{-- Actions --}}
                            <td class="text-end">
                                @if($match->estado === 'sugerido')
                                    <button class="btn btn-brand" style="padding:.3rem .6rem; font-size:12.5px;" data-confirm-match="{{ $match->id }}">
                                        <i class="fa-solid fa-check"></i>
                                    </button>
                                    <button class="btn btn-soft" style="padding:.3rem .6rem; font-size:12.5px; color:var(--danger);" data-reject-match="{{ $match->id }}">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                @else
                                    <span class="badge-status s-success"><i class="fa-solid fa-check"></i> Confirmado</span>
                                    <button class="btn btn-soft" style="padding:.3rem .5rem; font-size:12px; color:var(--danger);" data-reject-match="{{ $match->id }}" title="Deshacer">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
