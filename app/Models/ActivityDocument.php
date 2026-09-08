<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A validated PDF uploaded for an upload-type Calendario activity. See the
 * migration for the storage contract and ActivityDocumentService for how it's
 * created and validated.
 */
class ActivityDocument extends Model
{
    protected $fillable = [
        'client_id',
        'period_id',
        'activity_key',
        'path',
        'original_name',
        'rfc_ok',
        'sentido',
        'detalle',
        'sent_at',
        'sent_to',
        'uploaded_by',
    ];

    protected $casts = [
        'rfc_ok'  => 'boolean',
        'sent_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
