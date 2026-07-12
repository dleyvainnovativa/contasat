<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Parsed CFDI 4.0 invoice. Populated in Phase 1. Defined in Phase 0 so the
 * UUID (folio fiscal) threads through the system from the start.
 */
class Invoice extends Model
{
    protected $fillable = [
        'client_id', 'period_id', 'uuid', 'serie', 'folio',
        'emisor_rfc', 'emisor_nombre', 'receptor_rfc', 'receptor_nombre',
        'tipo', 'tipo_comprobante', 'metodo_pago', 'forma_pago', 'uso_cfdi',
        'subtotal', 'descuento', 'total', 'moneda', 'tipo_cambio',
        'fecha_emision', 'fecha_timbrado', 'cancelado', 'xml_original',
        'estado_conciliacion',
    ];

    protected function casts(): array
    {
        return [
            'subtotal'       => 'decimal:2',
            'descuento'      => 'decimal:2',
            'total'          => 'decimal:2',
            'tipo_cambio'    => 'decimal:6',
            'fecha_emision'  => 'datetime',
            'fecha_timbrado' => 'datetime',
            'cancelado'      => 'boolean',
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

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}
