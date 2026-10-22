<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/**
 * Sends FCM push to every registered staff device (T14 Phase 4).
 *
 * Reuses the Firebase Admin SDK (kreait) already wired for auth — the same
 * service-account credentials (FIREBASE_CREDENTIALS). Requires the Firebase
 * Cloud Messaging API to be enabled on the project.
 *
 * Best-effort: a send failure is logged, never thrown — these run inside the
 * dispatchAfterResponse job and must not break the guest turn. Tokens FCM
 * reports as invalid/unknown are pruned so the table self-cleans.
 */
class PushSender
{
    public function __construct(private Messaging $messaging) {}

    /**
     * @param array<string, mixed> $data payload the app reads to deep-link (type, ids)
     */
    public function send(string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::query()->pluck('token')->all();
        if (empty($tokens)) {
            return;
        }

        // FCM data values must be strings.
        $data = array_map(static fn ($v) => (string) $v, $data);

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        try {
            $report  = $this->messaging->sendMulticast($message, $tokens);
            $invalid = array_merge($report->invalidTokens(), $report->unknownTokens());

            if (! empty($invalid)) {
                DeviceToken::whereIn('token', $invalid)->delete();
            }
        } catch (\Throwable $e) {
            Log::warning('Push send failed', ['error' => $e->getMessage()]);
        }
    }
}
