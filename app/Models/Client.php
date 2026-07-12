<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A client (contribuyente) of the accountant. Root of the data hierarchy —
 * everything else scopes to a client.
 */
class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'rfc',
        'razon_social',
        'nombre_comercial',
        'regimen_fiscal',
        'codigo_postal',
        'email',
        'telefono',
        'activo',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    // Normalize RFC to uppercase on set — SAT RFCs are always uppercase.
    public function setRfcAttribute($value): void
    {
        $this->attributes['rfc'] = strtoupper(trim((string) $value));
    }

    public function periods(): HasMany
    {
        return $this->hasMany(Period::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function bankStatements(): HasMany
    {
        return $this->hasMany(BankStatement::class);
    }

    public function satCredential(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SatCredential::class);
    }

    public function satDownloadRequests(): HasMany
    {
        return $this->hasMany(SatDownloadRequest::class);
    }

    /** Display helper for the UI. */
    public function getDisplayNameAttribute(): string
    {
        return $this->nombre_comercial ?: $this->razon_social;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('razon_social', 'like', "%{$term}%")
              ->orWhere('nombre_comercial', 'like', "%{$term}%")
              ->orWhere('rfc', 'like', "%{$term}%");
        });
    }
}
