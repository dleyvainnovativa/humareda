<?php

namespace App\Http\Middleware;

use App\Services\FirebaseAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stateless auth for API routes via `Authorization: Bearer <firebase-id-token>`.
 * Verifies the token and binds the resolved User to the request for the
 * duration of the call. Blade routes use the standard session `auth` guard;
 * this is only for token-based API access.
 *
 * Register alias in bootstrap/app.php:  'firebase' => VerifyFirebaseToken::class
 */
class VerifyFirebaseToken
{
    public function __construct(private FirebaseAuthService $firebase) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Missing bearer token.'], 401);
        }

        $user = $this->firebase->resolveUser($token);

        if (! $user) {
            return response()->json(['message' => 'Invalid or unauthorized token.'], 401);
        }

        Auth::setUser($user);

        return $next($request);
    }
}
