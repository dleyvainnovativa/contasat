<?php

namespace App\Http\Middleware;

use App\Services\FirebaseAuth;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates requests via Firebase.
 *
 * Two modes, matching the stack requirements:
 *   - Blade views: the user is logged in via session (populated at login time
 *     after the token is verified once). If a session user exists, pass through.
 *   - API endpoints: accept a Bearer <Firebase ID token> header, verify it on
 *     each request, and resolve the local user.
 */
class VerifyFirebaseToken
{
    public function __construct(
        private readonly FirebaseAuth $firebase,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Session path (Blade): already authenticated.
        if (Auth::check()) {
            return $next($request);
        }

        // Bearer path (API): verify the Firebase ID token per request.
        $token = $request->bearerToken();

        if ($token && ($user = $this->firebase->resolveUser($token))) {
            Auth::setUser($user);

            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        return redirect()->route('login');
    }
}
