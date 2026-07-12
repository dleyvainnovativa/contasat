<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-bank extraction profile. Tunes the AI prompt for a specific bank's layout
 * and lets the extractor auto-detect which bank a statement is from.
 */
class BankProfile extends Model
{
    protected $fillable = [
        'banco', 'clave', 'detection_keywords', 'hints', 'formato_fecha', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'detection_keywords' => 'array',
            'activo'             => 'boolean',
        ];
    }
}
