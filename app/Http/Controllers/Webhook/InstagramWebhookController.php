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
 * Instagram DM webhook (T12 Phase C). Same Meta app + shared signature as
 * Messenger; inbound events arrive under object = "instagram" and share the
 * Messenger-Platform messaging envelope, so the engine reuses the same parser.
 *
 *   GET  /api/webhook/instagram  -> subscription challenge
 *   POST /api/webhook/instagram  -> inbound Instagram messaging events
 */
class InstagramWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.instagram.verify_token')) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        $raw = $request->getContent();

        if (! SignatureVerifier::verify($request->header('X-Hub-Signature-256'), $raw)) {
            Log::warning('Instagram webhook: bad signature.');
            return response('Invalid signature', 403);
        }

        $payload = json_decode($raw, true) ?: [];

        if (($payload['object'] ?? null) === 'instagram') {
            ProcessInboundMessage::dispatchAfterResponse($payload, Channel::Instagram);
        }

        return response('EVENT_RECEIVED', 200);
    }
}
