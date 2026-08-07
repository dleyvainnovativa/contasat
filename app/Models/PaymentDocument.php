<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One DoctoRelacionado from a CFDI de Pago — a link saying "payment invoice X
 * settled amount Y against the invoice with UUID Z."
 *
 * payment_invoice_id  the tipo-P complement that carries this link
 * paid_invoice_id     the invoice being paid, when we have it locally (nullable)
 * iddocumento         the paid invoice's UUID (the durable key, always present)
 * imp_pagado          the amount applied to that invoice by this payment
 */
class PaymentDocument extends Model
{
    protected $fillable = [
        'client_id', 'payment_invoice_id', 'pago_index',
        'fecha_pago', 'forma_pago', 'moneda', 'tipo_cambio', 'monto_pago', 'num_operacion',
        'iddocumento', 'paid_invoice_id', 'serie', 'folio', 'moneda_dr',
        'num_parcialidad', 'imp_saldo_ant', 'imp_pagado', 'imp_saldo_insoluto',
    ];

    protected function casts(): array
    {
        return [
            'fecha_pago'         => 'date',
            'tipo_cambio'        => 'decimal:6',
            'monto_pago'         => 'decimal:2',
            'imp_saldo_ant'      => 'decimal:2',
            'imp_pagado'         => 'decimal:2',
            'imp_saldo_insoluto' => 'decimal:2',
        ];
    }

    /** The pago complement (tipo P) that carries this link. */
    public function paymentInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'payment_invoice_id');
    }

    /** The invoice being paid, when present locally. */
    public function paidInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'paid_invoice_id');
    }

    public function isResolved(): bool
    {
        return $this->paid_invoice_id !== null;
    }
}
