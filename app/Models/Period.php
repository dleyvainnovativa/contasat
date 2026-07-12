<?php

namespace App\Models;

use App\Enums\PeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One accounting period (month/year) for a client. The unit of work that moves
 * through the pipeline; `status` drives the dashboard state machine.
 */
class Period extends Model
{
    protected $fillable = [
        'client_id',
        'year',
        'month',
        'status',
        'invoice_count',
        'movement_count',
        'matched_count',
        'unmatched_count',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status'    => PeriodStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function bankStatements(): HasMany
    {
        return $this->hasMany(BankStatement::class);
    }

    /** e.g. "Marzo 2026" */
    public function getLabelAttribute(): string
    {
        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                  7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

        return ($meses[$this->month] ?? $this->month) . ' ' . $this->year;
    }

    /** Progress percentage for the dashboard progress bar. */
    public function getProgressAttribute(): int
    {
        return (int) round(($this->status->step() / PeriodStatus::total_steps()) * 100);
    }
}
