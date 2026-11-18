<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's own active/inactive state for a shared (global) account.
 * See the account_client_status migration for the effective-state rule.
 */
class AccountClientStatus extends Model
{
    protected $table = 'account_client_status';

    protected $fillable = [
        'client_id',
        'account_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
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
