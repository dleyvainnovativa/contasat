<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A concepto line of a CFDI invoice. Populated in Phase 1. */
class InvoiceLine extends Model
{
    protected $fillable = [
        'invoice_id', 'clave_prod_serv', 'no_identificacion', 'descripcion',
        'cantidad', 'clave_unidad', 'valor_unitario', 'importe', 'descuento',
        'iva_trasladado', 'iva_retenido', 'isr_retenido',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'       => 'decimal:6',
            'valor_unitario' => 'decimal:6',
            'importe'        => 'decimal:2',
            'descuento'      => 'decimal:2',
            'iva_trasladado' => 'decimal:2',
            'iva_retenido'   => 'decimal:2',
            'isr_retenido'   => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
