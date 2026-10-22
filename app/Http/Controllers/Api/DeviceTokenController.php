<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FCM token registration for the Flutter staff app (T14 Phase 4 — push).
 * The app registers its token after login and removes it on logout.
 */
class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:20'],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id'      => $request->user()->id,
                'platform'     => $data['platform'] ?? 'android',
                'last_used_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string']]);
        DeviceToken::where('token', $data['token'])->delete();

        return response()->json(['ok' => true]);
    }
}
