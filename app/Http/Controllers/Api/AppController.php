<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile app — session + badge endpoints. All routes here sit behind the
 * `firebase` middleware (Authorization: Bearer <firebase-id-token>), so
 * $request->user() is the resolved, active staff User.
 *
 * Self-contained and additive: these controllers never touch the bot pipeline.
 */
class AppController extends Controller
{
    /** Who am I — lets the app confirm the token maps to an authorized user. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role ?? 'staff',
        ]);
    }

    /** Badge counts for the app shell (pending reservations + conversations needing a human). */
    public function summary(): JsonResponse
    {
        return response()->json([
            'pending_reservations' => (int) Reservation::pending()->upcoming()->count(),
            'attention'            => (int) Conversation::where('state', Conversation::STATE_HUMAN)->count(),
            'last_inbound_id'      => (int) Message::where('direction', Message::DIR_IN)->max('id'),
        ]);
    }
}
