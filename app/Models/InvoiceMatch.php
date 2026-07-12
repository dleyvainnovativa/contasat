<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The reconciliation link between a bank movement and an invoice. Named
 * InvoiceMatch (not Match — that's a PHP reserved keyword) but maps to the
 * `matches` table.
 *
 * `metodo` records how the link was made (deterministico / ia / manual) so every
 * reconciliation decision is auditable — important for SAT. `estado` tracks the
 * review lifecycle: sugerido -> confirmado | rechazado.
 */
class InvoiceMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'client_id', 'period_id', 'bank_movement_id', 'invoice_id',
        'metodo', 'score', 'estado', 'confirmed_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'score'        => 'decimal:4',
            'confirmed_at' => 'datetime',
        ];
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(BankMovement::class, 'bank_movement_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
