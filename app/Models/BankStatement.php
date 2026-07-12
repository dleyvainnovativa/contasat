<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A client's bank statement (PDF). Extracted in Phase 2. */
class BankStatement extends Model
{
    protected $fillable = [
        'client_id', 'period_id', 'banco', 'banco_perfil', 'numero_cuenta',
        'moneda', 'fecha_inicio', 'fecha_fin', 'saldo_inicial', 'saldo_final',
        'total_cargos', 'total_depositos', 'balance_cuadra', 'pdf_path',
        'extraccion_status', 'extraccion_error', 'extraido_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio'    => 'date',
            'fecha_fin'       => 'date',
            'saldo_inicial'   => 'decimal:2',
            'saldo_final'     => 'decimal:2',
            'total_cargos'    => 'decimal:2',
            'total_depositos' => 'decimal:2',
            'balance_cuadra'  => 'boolean',
            'extraido_at'     => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(BankMovement::class);
    }

    public function statusLabel(): string
    {
        return match ($this->extraccion_status) {
            'pendiente'  => 'En cola',
            'procesando' => 'Extrayendo',
            'ok'         => 'Extraído',
            'revision'   => 'Requiere revisión',
            'error'      => 'Error',
            default      => $this->extraccion_status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->extraccion_status) {
            'pendiente'  => 'secondary',
            'procesando' => 'info',
            'ok'         => 'success',
            'revision'   => 'warning',
            'error'      => 'danger',
            default      => 'secondary',
        };
    }
}
