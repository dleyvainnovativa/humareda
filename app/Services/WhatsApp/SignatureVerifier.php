<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Verifies Meta's X-Hub-Signature-256 header against the raw request body,
 * using the app secret. This proves the webhook actually came from Meta.
 *
 * If no app secret is configured AND the app is in local/testing, verification
 * is skipped with a warning so early test-number setup isn't blocked. In
 * production the secret MUST be set — an unset secret there is treated as a
 * failure.
 */
class SignatureVerifier
{
    public static function verify(?string $header, string $rawBody): bool
    {
        // One Meta app hosts WhatsApp + Messenger + Instagram, so one app secret
        // verifies all three. Falls back to the WhatsApp secret for compatibility.
        $secret = (string) (config('services.meta.app_secret') ?: config('services.whatsapp.app_secret'));

        if ($secret === '') {
            if (app()->environment('local', 'testing')) {
                Log::warning('Meta signature check skipped: no app_secret set (local/testing).');
                return true;
            }
            Log::error('Meta signature check failed: app_secret not configured in production.');
            return false;
        }

        if (! $header || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $header);
    }
}
