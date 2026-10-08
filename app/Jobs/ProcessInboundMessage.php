<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Setting;
use App\Services\WhatsApp\ConversationResolver;
use App\Services\WhatsApp\InboundParser;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Bus\Queueable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Processes an inbound webhook payload AFTER the HTTP response is sent to Meta
 * (dispatched via dispatchAfterResponse). Deliberately NOT ShouldQueue: it runs
 * in-process, which suits shared hosting (no queue daemon needed for replies).
 *
 * Responsibilities in T2:
 *   - dedupe on wa_message_id (Meta retries duplicates)
 *   - log every inbound message
 *   - resolve/update the contact + conversation
 *   - mark the message read
 *   - stay SILENT if the conversation is handed to a human
 *   - otherwise send a PLACEHOLDER acknowledgement
 *
 * The placeholder responder in handleMessage() is replaced by the real
 * intent/booking engine in T4.
 */
class ProcessInboundMessage
{
    use Dispatchable, Queueable;

    public function __construct(private array $payload) {}

    public function handle(
        ConversationResolver $resolver,
        WhatsAppClient $wa,
    ): void {
        $parsed = InboundParser::parse($this->payload);

        // Status callbacks (delivered/read receipts) — logged only for now.
        foreach ($parsed['statuses'] as $status) {
            Log::debug('WhatsApp status', $status);
        }

        foreach ($parsed['messages'] as $msg) {
            if (! $msg['wamid'] || ! $msg['from']) {
                continue;
            }

            [$inbound, $isNew] = $this->logInbound($resolver, $msg);

            // Duplicate delivery (Meta retry) — already processed, skip.
            if (! $isNew) {
                Log::info('WhatsApp duplicate message ignored', ['wamid' => $msg['wamid']]);
                continue;
            }

            // Blue ticks (best effort).
            $wa->markRead($msg['wamid']);

            $contact      = $inbound->contact;
            $conversation = $contact->conversation;

            // Handed to a human: bot stays quiet, message is still logged above.
            if ($conversation && $conversation->state === Conversation::STATE_HUMAN) {
                continue;
            }

            if (! Setting::get('bot_enabled', true)) {
                continue;
            }

            $this->respondPlaceholder($wa, $contact, $msg);
        }
    }

    /**
     * Idempotent inbound logging. The unique index on wa_message_id makes this
     * safe against concurrent retries: a duplicate insert throws and we treat
     * it as "already seen".
     *
     * @return array{0: Message, 1: bool}  [message, wasCreated]
     */
    private function logInbound(ConversationResolver $resolver, array $msg): array
    {
        $existing = Message::where('wa_message_id', $msg['wamid'])->first();
        if ($existing) {
            return [$existing->loadMissing('contact.conversation'), false];
        }

        $contact = $resolver->resolve($msg['from'], $msg['name']);

        try {
            $message = Message::create([
                'contact_id'    => $contact->id,
                'direction'     => Message::DIR_IN,
                'type'          => $msg['type'],
                'body'          => $msg['body'],
                'wa_message_id' => $msg['wamid'],
                'sender'        => 'guest',
                'payload'       => $msg['raw'],
                'created_at'    => now(),
            ]);
        } catch (QueryException $e) {
            // Unique violation => another retry inserted it first.
            $existing = Message::where('wa_message_id', $msg['wamid'])->first();
            return [$existing?->loadMissing('contact.conversation') ?? new Message(), false];
        }

        $message->setRelation('contact', $contact);

        return [$message, true];
    }

    /**
     * TEMPORARY T2 responder — proves the round trip end to end.
     * Replaced by the LLM intent + booking engine in T4.
     */
    private function respondPlaceholder(WhatsAppClient $wa, Contact $contact, array $msg): void
    {
        if ($msg['type'] === 'text' && $msg['body']) {
            $reply = "Recibí tu mensaje: \"{$msg['body']}\".\n\n"
                . "(Asistente en configuración — la lógica de reservaciones se activa en la siguiente etapa.)";
        } elseif ($msg['type'] === 'audio') {
            $reply = "Recibí tu nota de voz. Por ahora estoy en configuración; pronto podré escucharla.";
        } else {
            $reply = "Recibí tu mensaje. Por ahora estoy en configuración.";
        }

        $wamid = $wa->sendText($contact->wa_id, $reply);

        Message::create([
            'contact_id'    => $contact->id,
            'direction'     => Message::DIR_OUT,
            'type'          => 'text',
            'body'          => $reply,
            'wa_message_id' => $wamid,
            'sender'        => 'bot',
            'created_at'    => now(),
        ]);
    }
}
