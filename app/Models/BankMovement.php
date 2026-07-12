<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A single line of a bank statement. Extracted in Phase 2, matched in Phase 3. */
class BankMovement extends Model
{
    protected $fillable = [
        'bank_statement_id', 'client_id', 'fecha', 'descripcion', 'referencia',
        'cargo', 'deposito', 'saldo', 'estado_conciliacion', 'account_id', 'confianza',
    ];

    protected function casts(): array
    {
        return [
            'fecha'     => 'date',
            'cargo'     => 'decimal:2',
            'deposito'  => 'decimal:2',
            'saldo'     => 'decimal:2',
            'confianza' => 'decimal:4',
        ];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
