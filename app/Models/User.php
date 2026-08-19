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
        'preferences'
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
            'preferences' => 'array',
        ];
    }

    /** Get a preference by key with a default. */
    public function pref(string $key, mixed $default = null): mixed
    {
        return data_get($this->preferences, $key, $default);
    }

    /** Set a preference and persist. */
    public function setPref(string $key, mixed $value): void
    {
        $prefs = $this->preferences ?? [];
        data_set($prefs, $key, $value);
        $this->preferences = $prefs;
        $this->save();
    }
}
