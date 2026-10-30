<?php

namespace App\Services\Push;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/**
 * Thin wrapper over Firebase Cloud Messaging (kreait/laravel-firebase, already a
 * project dependency — uses the existing FIREBASE_CREDENTIALS admin SDK file).
 *
 * Best-effort by design: any failure is logged and swallowed so the push scanner
 * (a cron job) never throws. Tokens FCM reports as invalid/unregistered are
 * pruned so the table self-cleans after reinstalls or uninstalls.
 */
class FcmPusher
{
    /**
     * Push one notification to every registered device. Returns the number of
     * tokens FCM accepted.
     *
     * @param  array<string, string>  $data  extra key/value data (string values)
     */
    public function notifyAll(string $title, string $body, array $data = []): int
    {
        $tokens = DeviceToken::query()->pluck('token')->all();

        if (empty($tokens)) {
            return 0;
        }

        return $this->send($tokens, $title, $body, $data);
    }

    /**
     * @param  array<int, string>     $tokens
     * @param  array<string, string>  $data
     */
    public function send(array $tokens, string $title, string $body, array $data = []): int
    {
        try {
            /** @var Messaging $messaging */
            $messaging = app(Messaging::class);
        } catch (\Throwable $e) {
            Log::warning('FCM not configured; push skipped.', ['error' => $e->getMessage()]);
            return 0;
        }

        // FCM data payload values must be strings.
        $data = array_map(fn ($v) => (string) $v, $data);

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data)
            // Android: group + make it a high-priority heads-up notification.
            ->withAndroidConfig([
                'priority'     => 'high',
                'notification' => [
                    'channel_id' => 'humareda_alerts',
                    'sound'      => 'default',
                ],
            ]);

        $delivered = 0;
        $invalid   = [];

        foreach (array_chunk($tokens, 500) as $chunk) {
            try {
                $report = $messaging->sendMulticast($message, $chunk);
                $delivered += $report->successes()->count();
                $invalid = array_merge(
                    $invalid,
                    $report->invalidTokens(),
                    $report->unknownTokens(),
                );
            } catch (\Throwable $e) {
                Log::warning('FCM multicast failed.', ['error' => $e->getMessage()]);
            }
        }

        if (! empty($invalid)) {
            DeviceToken::whereIn('token', $invalid)->delete();
            Log::info('FCM pruned stale tokens.', ['count' => count($invalid)]);
        }

        return $delivered;
    }
}
