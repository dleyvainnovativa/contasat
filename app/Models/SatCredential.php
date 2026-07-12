<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A client's e.firma (FIEL) for SAT Descarga Masiva.
 *
 * The .cer contents, .key contents, and key password are encrypted at rest using
 * Laravel's `encrypted` cast (AES-256, keyed on APP_KEY). The security model
 * therefore rests entirely on APP_KEY never living alongside a database dump.
 *
 * Only a FIEL works for descarga masiva — a CSD (sello digital) is rejected by
 * SAT. `is_fiel` records which one was uploaded so the UI can warn early.
 */
class SatCredential extends Model
{
    protected $fillable = [
        'client_id', 'cer_contents', 'key_contents', 'key_password',
        'cer_rfc', 'cer_serial', 'valid_from', 'valid_to', 'is_fiel',
    ];

    protected $hidden = ['cer_contents', 'key_contents', 'key_password'];

    protected function casts(): array
    {
        return [
            'cer_contents' => 'encrypted',
            'key_contents' => 'encrypted',
            'key_password' => 'encrypted',
            'valid_from'   => 'datetime',
            'valid_to'     => 'datetime',
            'is_fiel'      => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Usable only if it's a FIEL and still within its validity window. */
    public function isUsable(): bool
    {
        if (! $this->is_fiel) {
            return false;
        }

        $now = now();

        return (! $this->valid_from || $this->valid_from->lte($now))
            && (! $this->valid_to || $this->valid_to->gte($now));
    }

    public function statusLabel(): string
    {
        if (! $this->is_fiel) {
            return 'No es e.firma (es CSD)';
        }
        if ($this->valid_to && $this->valid_to->isPast()) {
            return 'Vencida';
        }
        if ($this->valid_to && $this->valid_to->diffInDays(now()) <= 30) {
            return 'Por vencer';
        }

        return 'Vigente';
    }

    public function statusColor(): string
    {
        if (! $this->is_fiel || ($this->valid_to && $this->valid_to->isPast())) {
            return 'danger';
        }
        if ($this->valid_to && $this->valid_to->diffInDays(now()) <= 30) {
            return 'warning';
        }

        return 'success';
    }
}
