<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Contract\Auth as FirebaseAuthContract;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

/**
 * Bridges Firebase Authentication to Laravel. Verifies the Firebase ID token
 * (issued client-side by the Firebase JS SDK) and maps it to a local User row,
 * creating one on first sight. This is the same pattern used across projects:
 * Firebase is the identity provider, MySQL holds the app-side profile.
 *
 * Requires kreait/firebase-php:
 *   composer require kreait/firebase-php
 * and the FirebaseServiceProvider binding (config/firebase.php credentials).
 */
class FirebaseAuth
{
    public function __construct(
        private readonly FirebaseAuthContract $auth,
    ) {}

    /**
     * Verify a Firebase ID token and return the matching local user.
     * Returns null when the token is invalid or expired.
     */
    public function resolveUser(string $idToken): ?User
    {
        try {
            $verified = $this->auth->verifyIdToken($idToken);
        } catch (FailedToVerifyToken) {
            return null;
        }

        $uid    = $verified->claims()->get('sub');
        $email  = $verified->claims()->get('email');
        $name   = $verified->claims()->get('name') ?? $email;

        return User::updateOrCreate(
            ['firebase_uid' => $uid],
            ['email' => $email, 'name' => $name],
        );
    }
    
}
