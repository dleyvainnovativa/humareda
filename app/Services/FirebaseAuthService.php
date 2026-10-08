<?php

namespace App\Services;

use App\Models\User;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

/**
 * Verifies Firebase ID tokens and maps them onto local User records.
 *
 * Flow:
 *   Browser signs in with Firebase JS SDK -> gets an ID token ->
 *   posts it here -> we verify it against Firebase -> find/create the
 *   local user -> caller logs them into the Laravel session guard.
 */
class FirebaseAuthService
{
    public function __construct(private FirebaseAuth $auth) {}

    /**
     * Verify an ID token and return the matching local user, or null if the
     * token is invalid or the user isn't provisioned/active.
     *
     * We do NOT auto-create arbitrary users: staff must be seeded/invited,
     * otherwise anyone with a Google account could sign in. Set
     * FIREBASE_AUTO_PROVISION=true only for local dev if you want that.
     */
    public function resolveUser(string $idToken): ?User
    {
        try {
            $verified = $this->auth->verifyIdToken($idToken);
        } catch (FailedToVerifyToken|\Throwable $e) {
            return null;
        }

        $uid    = $verified->claims()->get('sub');
        $email  = $verified->claims()->get('email');
        $name   = $verified->claims()->get('name') ?? strtok((string) $email, '@');

        $user = User::where('firebase_uid', $uid)
            ->orWhere('email', $email)
            ->first();

        if (! $user && config('services.firebase.auto_provision')) {
            $user = User::create([
                'firebase_uid' => $uid,
                'name'         => $name,
                'email'        => $email,
                'role'         => 'staff',
            ]);
        }

        if (! $user || ! $user->is_active) {
            return null;
        }

        // Backfill the uid if the user was pre-seeded by email only.
        if ($user->firebase_uid !== $uid) {
            $user->firebase_uid = $uid;
        }
        $user->last_login_at = now();
        $user->save();

        return $user;
    }
}
