<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\FirebaseAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Bridges Firebase sign-in to a Laravel session.
 *
 *   GET  /login            -> show the Firebase sign-in page (Blade)
 *   POST /auth/firebase    -> receive ID token (JS), verify, start session
 *   POST /logout           -> end session
 */
class FirebaseLoginController extends Controller
{
    public function __construct(private FirebaseAuthService $firebase) {}

    public function show(): mixed
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Called by resources/js/firebase.js after a successful Firebase sign-in.
     * Expects { id_token: "..." }. Returns JSON so the front-end can redirect.
     */
    public function authenticate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        $user = $this->firebase->resolveUser($data['id_token']);

        if (! $user) {
            return response()->json([
                'ok'      => false,
                'message' => 'No autorizado. Contacta al administrador.',
            ], 401);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json([
            'ok'       => true,
            'redirect' => route('dashboard'),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
