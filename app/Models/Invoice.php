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
        'client_id',
        'period_id',
        'uuid',
        'serie',
        'folio',
        'emisor_rfc',
        'emisor_nombre',
        'receptor_rfc',
        'receptor_nombre',
        'tipo',
        'tipo_comprobante',
        'metodo_pago',
        'forma_pago',
        'uso_cfdi',
        'subtotal',
        'descuento',
        'total',
        'moneda',
        'tipo_cambio',
        'fecha_emision',
        'fecha_timbrado',
        'cancelado',
        'xml_original',
        'estado_conciliacion',
        'cuenta_contable_id',
        'cuenta_abono_id',
        'clasificacion',
        'iva_trasladado',
        'iva_retenido',
        'isr_retenido'
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
            'iva_trasladado'       => 'decimal:2',
            'iva_retenido'       => 'decimal:2',
            'isr_retenido'       => 'decimal:2',
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

    /** Pólizas generated for this invoice (provisión / cobro), distinguished by tipo. */
    public function polizas(): HasMany
    {
        return $this->hasMany(Poliza::class);
    }

    /**
     * Payment-complement links (CEP) that settle this invoice, matched on UUID.
     * A DoctoRelacionado carries iddocumento = this invoice's UUID.
     */
    public function paymentDocuments(): HasMany
    {
        return $this->hasMany(PaymentDocument::class, 'iddocumento', 'uuid');
    }

    public function cuentaContable(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class, 'cuenta_contable_id');
    }

    public function cuentaAbono(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class, 'cuenta_abono_id');
    }

    /**
     * All line descriptions concatenated with " | ", for the concepts column in
     * the ingreso/gasto lists. Reads from the loaded `lines` relation, so callers
     * should eager-load it to avoid N+1.
     */
    public function getConceptosResumenAttribute(): string
    {
        return $this->lines
            ->pluck('descripcion')
            ->filter()
            ->map(fn($d) => trim($d))
            ->implode(' | ');
    }

    /** ESTADO SAT column: Vigente / Cancelado. */
    public function getEstadoSatAttribute(): string
    {
        return $this->cancelado ? 'Cancelado' : 'Vigente';
    }

    /**
     * IVA-base breakdown for the 4 columns (16% / 0% / exento / no objeto).
     * Sums each line's importe into its bucket using invoice_lines.iva_base_tipo.
     * Until 4b populates that column, buckets are null (rendered as "—").
     *
     * @return array{16:?float, 0:?float, exento:?float, no_objeto:?float}
     */
    public function getIvaBaseBreakdownAttribute(): array
    {
        $buckets = ['16' => null, '0' => null, 'exento' => null, 'no_objeto' => null];

        foreach ($this->lines as $line) {
            $tipo = $line->iva_base_tipo ?? null;
            if ($tipo === null || ! array_key_exists($tipo, $buckets)) {
                continue; // unclassified line (pre-4b) — leave buckets untouched
            }
            $buckets[$tipo] = ($buckets[$tipo] ?? 0) + (float) $line->importe;
        }

        return $buckets;
    }

    /** The póliza de provisión reference (e.g. "Dr-1"), or null if not generated. */
    public function getProvisionRefAttribute(): ?string
    {
        return $this->polizas->firstWhere('tipo', 'provision')?->num_iden;
    }

    /** The póliza de ingreso/cobro reference (e.g. "Ig-1"), or null. */
    public function getIngresoRefAttribute(): ?string
    {
        return $this->polizas->firstWhere('tipo', 'cobro')?->num_iden;
    }

    /**
     * Payment (CEP) block for the wide table. Aggregates the DoctoRelacionado
     * links that settle this invoice. Null when no complement is linked (PUE
     * invoices show blank — we don't synthesize a payment).
     *
     * @return array{recibo:bool, fecha:?string, importe:?float}
     */
    public function getPagoResumenAttribute(): array
    {
        $docs = $this->paymentDocuments;

        if ($docs->isEmpty()) {
            return ['recibo' => false, 'fecha' => null, 'importe' => null];
        }

        return [
            'recibo'  => true,
            'fecha'   => optional($docs->sortBy('fecha_pago')->last()->fecha_pago)->format('Y-m-d'),
            'importe' => (float) $docs->sum('imp_pagado'),
        ];
    }
}
