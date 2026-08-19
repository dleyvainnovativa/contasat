<?php


/**
 * ====================================================================
 * 2. NEW FILE: app/Http/Controllers/PreferenceController.php
 * ====================================================================
 */

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Persists small cross-device UI preferences (e.g. which table columns are
 * hidden). Deliberately narrow: only whitelisted keys, so this can't become a
 * dumping ground or an injection vector into arbitrary preference paths.
 */
class PreferenceController extends Controller
{
    /** Preference keys the client is allowed to set. */
    private const ALLOWED = [
        'accounts_columns',
        'global_accounts_columns',
    ];

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key'   => ['required', 'string'],
            'value' => ['present'],   // array or scalar
        ]);

        if (! in_array($data['key'], self::ALLOWED, true)) {
            return response()->json(['message' => 'Preferencia no permitida.'], 422);
        }

        $user = Auth::user();
        $user->setPref($data['key'], $data['value']);

        return response()->json(['ok' => true]);
    }
}
