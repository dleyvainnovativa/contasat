<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The learned "RFC -> account" memory. When the accountant confirms which account
 * a counterparty's movements post to, we remember it here so the next time that
 * RFC appears the account is pre-filled. This is what lets account assignment
 * scale across 50+ clients without repeating the same decisions every month.
 */
class AccountDefault extends Model
{
    protected $fillable = [
        'client_id', 'rfc_contraparte', 'account_id', 'veces_usado', 'ultimo_uso_at',
    ];

    protected function casts(): array
    {
        return [
            'ultimo_uso_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
