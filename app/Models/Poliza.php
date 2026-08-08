<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A persisted póliza. Provisión (accrual) and cobro (cash) for the same invoice
 * are two rows sharing invoice_id, distinguished by tipo.
 */
class Poliza extends Model
{
    protected $fillable = [
        'client_id', 'period_id', 'invoice_id', 'tipo', 'num_iden', 'fecha',
        'concepto', 'origen_pago', 'bank_movement_id', 'payment_uuid',
        'total_cargo', 'total_abono', 'cuadra',
    ];

    protected function casts(): array
    {
        return [
            'fecha'       => 'date',
            'total_cargo' => 'decimal:2',
            'total_abono' => 'decimal:2',
            'cuadra'      => 'boolean',
        ];
    }

    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function period(): BelongsTo { return $this->belongsTo(Period::class); }
    public function invoice(): BelongsTo { return $this->belongsTo(Invoice::class); }
    public function lines(): HasMany { return $this->hasMany(PolizaLine::class); }

    public function tipoLabel(): string
    {
        return match ($this->tipo) {
            'provision' => 'Provisión',
            'cobro'     => 'Cobro',
            'egreso'    => 'Egreso',
            'pago'      => 'Pago',
            default     => $this->tipo,
        };
    }
}
