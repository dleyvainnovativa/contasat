<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One SAT descarga masiva request, tracked through its four-step lifecycle:
 *   solicitando -> verificando -> descargando -> completado | error
 *
 * SAT's verification can take minutes to 72 hours, so this lives entirely on the
 * queue: a job submits the query, a scheduled poller verifies and downloads.
 */
class SatDownloadRequest extends Model
{
    protected $fillable = [
        'client_id', 'period_id', 'download_type', 'request_type',
        'period_start', 'period_end', 'sat_request_id', 'package_ids',
        'status', 'error_message', 'sat_status_code', 'sat_status_message',
        'cfdi_count', 'packages_downloaded', 'imported', 'skipped',
        'verify_attempts', 'last_verified_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'package_ids'      => 'array',
            'period_start'     => 'datetime',
            'period_end'       => 'datetime',
            'last_verified_at' => 'datetime',
            'completed_at'     => 'datetime',
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
        return in_array($this->status, ['completado', 'error'], true);
    }

    /** Still waiting on SAT to build the packages. */
    public function isPending(): bool
    {
        return in_array($this->status, ['solicitando', 'verificando', 'descargando'], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'solicitando' => 'Enviando solicitud',
            'verificando' => 'Esperando al SAT',
            'descargando' => 'Descargando paquetes',
            'completado'  => 'Completado',
            'error'       => 'Error',
            default       => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'solicitando' => 'info',
            'verificando' => 'warning',
            'descargando' => 'primary',
            'completado'  => 'success',
            'error'       => 'danger',
            default       => 'secondary',
        };
    }

    public function typeLabel(): string
    {
        $dir = $this->download_type === 'issued' ? 'Emitidas' : 'Recibidas';
        $kind = $this->request_type === 'xml' ? 'XML' : 'Metadata';

        return "{$dir} · {$kind}";
    }
}
