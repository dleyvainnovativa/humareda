<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundMessage;
use App\Services\WhatsApp\SignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Cloud API webhook.
 *
 *   GET  /api/webhook/whatsapp  -> Meta's subscription challenge
 *   POST /api/webhook/whatsapp  -> inbound messages + status callbacks
 *
 * The POST handler does the minimum synchronously (signature check), then
 * hands the payload to a job via dispatchAfterResponse so Meta gets its 200
 * immediately and the LLM/reply work happens after the connection closes.
 * This is the shared-hosting-friendly alternative to a queue daemon.
 */
class WhatsAppWebhookController extends Controller
{
    /**
     * Verification handshake. Meta calls this once when you set the webhook URL.
     */
    public function verify(Request $request): Response
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response((string) $challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Forbidden', 403);
    }

    /**
     * Inbound events. Always 200 on authentic payloads (even if we can't act
     * on a particular message) so Meta doesn't retry-storm; 403 only when the
     * signature is invalid.
     */
    public function handle(Request $request): Response
    {
        $raw = $request->getContent();

        if (! SignatureVerifier::verify($request->header('X-Hub-Signature-256'), $raw)) {
            Log::warning('WhatsApp webhook: bad signature.');
            return response('Invalid signature', 403);
        }

        $payload = json_decode($raw, true) ?: [];

        // Heavy lifting runs after the response is flushed to Meta.
        ProcessInboundMessage::dispatchAfterResponse($payload);

        return response('EVENT_RECEIVED', 200);
    }
}
