<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single uploaded CFDI package (ZIP or XML) tracked through async processing.
 */
class CfdiUpload extends Model
{
    protected $fillable = [
        'client_id', 'period_id', 'original_name', 'stored_path', 'size_bytes',
        'status', 'imported', 'skipped', 'failed', 'errors', 'fatal_error', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'errors'       => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['done', 'failed'], true);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'pending'    => 'secondary',
            'processing' => 'info',
            'done'       => 'success',
            'failed'     => 'danger',
            default      => 'secondary',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'    => 'En cola',
            'processing' => 'Procesando',
            'done'       => 'Completado',
            'failed'     => 'Error',
            default      => $this->status,
        };
    }
}
