<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON API for the Flutter staff app (T14 Phase 4) — session + oversight home.
 *
 * `me` confirms the bearer token (via the `firebase` guard) and returns the
 * signed-in staff member plus the two counts the app's home screen leads with:
 * conversations needing attention, and reservations awaiting authorization.
 */
class MeController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => $user->role ?? 'staff',
            ],
            'counts' => [
                'attention'            => Conversation::where('state', Conversation::STATE_HUMAN)->count(),
                'pending_reservations' => Reservation::pending()->upcoming()->count(),
            ],
        ]);
    }
}
