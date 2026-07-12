<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Local user record. Authentication is delegated to Firebase; this model holds
 * the app-side profile and links via firebase_uid. In Phase 0 there is a single
 * user (the accountant), but the model is built to allow more later.
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'firebase_uid',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
