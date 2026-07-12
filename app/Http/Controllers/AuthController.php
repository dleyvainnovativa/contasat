<?php

namespace App\Http\Controllers;

use App\Services\FirebaseAuth;
use App\Services\WorkContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Session login flow for Blade views. The Firebase JS SDK authenticates the user
 * in the browser and posts the resulting ID token here once; we verify it, resolve
 * the local user, and log them into a Laravel session. Subsequent requests use the
 * session (no token round-trip per request for the web UI).
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly FirebaseAuth $firebase,
        private readonly WorkContext $context,
    ) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    /** Called by the front-end after Firebase sign-in succeeds. */
    public function session(Request $request): JsonResponse
    {
        $request->validate(['id_token' => ['required', 'string']]);

        $user = $this->firebase->resolveUser($request->string('id_token'));

        if (! $user) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json(['ok' => true, 'redirect' => route('dashboard')]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->context->clear();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'redirect' => route('login')]);
    }
}
