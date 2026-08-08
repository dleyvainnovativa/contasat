<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a póliza. cargo XOR abono; the other is 0. */
class PolizaLine extends Model
{
    protected $fillable = [
        'poliza_id', 'account_id', 'numero_cuenta', 'nombre_cuenta',
        'concepto', 'uuid', 'cargo', 'abono',
    ];

    protected function casts(): array
    {
        return ['cargo' => 'decimal:2', 'abono' => 'decimal:2'];
    }

    public function poliza(): BelongsTo { return $this->belongsTo(Poliza::class); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
}
