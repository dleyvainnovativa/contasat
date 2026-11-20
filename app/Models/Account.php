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
        'tipo',
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

    protected static function booted(): void
    {
        // Keep sort_key derived from numero_cuenta on every write (create/import/edit).
        static::saving(function (Account $account) {
            $account->sort_key = self::sortKeyFor($account->numero_cuenta);
        });
    }

    /**
     * Natural-sort key for a dotted account code: each numeric segment is
     * zero-padded to 6 digits so a plain string ORDER BY sorts numerically and
     * keeps parents before their children. Non-numeric segments (rare) are
     * lowercased and kept as-is.
     */
    public static function sortKeyFor(?string $numero): string
    {
        $numero = trim((string) $numero);
        if ($numero === '') {
            return '';
        }

        $out = [];
        foreach (explode('.', $numero) as $segment) {
            $segment = trim($segment);
            if ($segment !== '' && ctype_digit($segment)) {
                $out[] = str_pad($segment, 6, '0', STR_PAD_LEFT);
            } else {
                $out[] = strtolower($segment);
            }
        }

        return implode('.', $out);
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

    /** Per-client active/inactive overlays for this account. */
    public function clientStatuses(): HasMany
    {
        return $this->hasMany(AccountClientStatus::class);
    }

    /**
     * Restrict to accounts effectively ACTIVE for a client: the per-client
     * overlay when one exists, otherwise the account's own `activo`. Pure WHERE
     * (no join), so it composes with forClient()/es_afectable and column selects.
     */
    public function scopeActiveFor($query, int $clientId)
    {
        return $query->where(function ($q) use ($clientId) {
            // Overlay row for this client marks it active...
            $q->whereIn('accounts.id', function ($sub) use ($clientId) {
                $sub->select('account_id')->from('account_client_status')
                    ->where('client_id', $clientId)->where('activo', true);
            })
            // ...or there's no overlay row and the account's own flag is active.
            ->orWhere(function ($q2) use ($clientId) {
                $q2->where('accounts.activo', true)
                   ->whereNotExists(function ($sub) use ($clientId) {
                       $sub->selectRaw('1')->from('account_client_status')
                           ->whereColumn('account_client_status.account_id', 'accounts.id')
                           ->where('account_client_status.client_id', $clientId);
                   });
            });
        });
    }

    /**
     * The inverse of scopeActiveFor: accounts effectively INACTIVE for a client
     * (overlay says inactive, or no overlay and the account's own flag is off).
     */
    public function scopeInactiveFor($query, int $clientId)
    {
        return $query->where(function ($q) use ($clientId) {
            // Overlay row for this client marks it inactive...
            $q->whereIn('accounts.id', function ($sub) use ($clientId) {
                $sub->select('account_id')->from('account_client_status')
                    ->where('client_id', $clientId)->where('activo', false);
            })
            // ...or there's no overlay row and the account's own flag is inactive.
            ->orWhere(function ($q2) use ($clientId) {
                $q2->where('accounts.activo', false)
                   ->whereNotExists(function ($sub) use ($clientId) {
                       $sub->selectRaw('1')->from('account_client_status')
                           ->whereColumn('account_client_status.account_id', 'accounts.id')
                           ->where('account_client_status.client_id', $clientId);
                   });
            });
        });
    }

    /** Effective active state for a client, given a prefetched overlay map [account_id => activo]. */
    public function effectiveActivo(array $overlay): bool
    {
        return array_key_exists($this->id, $overlay) ? (bool) $overlay[$this->id] : (bool) $this->activo;
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
