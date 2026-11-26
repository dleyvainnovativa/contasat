<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's own state for a shared (global) account: active/inactive, and the
 * client's saldo inicial (+ its period) for that account. See the
 * account_client_status migrations for the effective-state rule.
 */
class AccountClientStatus extends Model
{
    protected $table = 'account_client_status';

    protected $fillable = [
        'client_id',
        'account_id',
        'activo',
        'saldo_inicial',
        'saldo_inicial_year',
        'saldo_inicial_month',
    ];

    protected function casts(): array
    {
        return [
            'activo'        => 'boolean',
            'saldo_inicial' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
