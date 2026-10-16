<?php

namespace App\Http\Controllers\Webhook;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundMessage;
use App\Services\WhatsApp\SignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Messenger (Page DM) webhook — mirrors the WhatsApp controller.
 *
 *   GET  /api/webhook/messenger  -> subscription challenge
 *   POST /api/webhook/messenger  -> inbound Page messaging events
 *
 * Same Meta app as WhatsApp, so the X-Hub-Signature-256 HMAC uses the shared
 * app secret (SignatureVerifier). Heavy work runs after the 200 is flushed.
 */
class MessengerWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.messenger.verify_token')) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        $raw = $request->getContent();

        if (! SignatureVerifier::verify($request->header('X-Hub-Signature-256'), $raw)) {
            Log::warning('Messenger webhook: bad signature.');
            return response('Invalid signature', 403);
        }

        $payload = json_decode($raw, true) ?: [];

        // Page DMs arrive under object = "page". Ignore other objects here.
        if (($payload['object'] ?? null) === 'page') {
            ProcessInboundMessage::dispatchAfterResponse($payload, Channel::Messenger);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
