<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A single account in a client's chart of accounts (catálogo de cuentas).
 * Maps to a SAT CodAgrupador. Self-referencing for hierarchy.
 */
class Account extends Model
{
    protected $fillable = [
        'client_id',
        'parent_id',
        'codigo_agrupador',
        'numero_cuenta',
        'nombre',
        'nivel',
        'naturaleza',
        'es_afectable',
        'activo',
        'rfc_asociado',
        'auto_generada',

    ];

    protected function casts(): array
    {
        return [
            'es_afectable' => 'boolean',
            'activo'       => 'boolean',
            'auto_generada' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    public function getFullLabelAttribute(): string
    {
        return "{$this->numero_cuenta} — {$this->nombre}";
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where(function ($q) use ($clientId) {
            $q->whereNull('client_id')->orWhere('client_id', $clientId);
        });
    }

    /** Only the shared SAT catalog rows. */
    public function scopeGlobal($query)
    {
        return $query->whereNull('client_id');
    }

    /** Only a specific client's minted subaccounts. */
    public function scopeClientOwned($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function isGlobal(): bool
    {
        return $this->client_id === null;
    }
}
