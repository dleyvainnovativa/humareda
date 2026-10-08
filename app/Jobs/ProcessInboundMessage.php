<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Setting;
use App\Services\AI\TranscriptionService;
use App\Services\Bot\ConversationEngine;
use App\Services\Bot\InboundRouter;
use App\Services\Bot\Replies;
use App\Services\WhatsApp\ConversationResolver;
use App\Services\WhatsApp\InboundParser;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Bus\Queueable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Processes an inbound webhook payload after the HTTP response is sent to Meta.
 * (T7) Adds voice-note transcription: audio is downloaded + transcribed, and
 * the transcript flows into the same ConversationEngine as typed text. Non-text
 * we can't use (stickers, locations, failed audio) gets a polite "please type".
 *
 * Replaces the T4 ProcessInboundMessage.
 */
class ProcessInboundMessage
{
    use Dispatchable, Queueable;

    public function __construct(private array $payload) {}

    public function handle(
        ConversationResolver $resolver,
        WhatsAppClient $wa,
        ConversationEngine $engine,
        TranscriptionService $transcription,
    ): void {
        $parsed = InboundParser::parse($this->payload);

        foreach ($parsed['statuses'] as $status) {
            Log::debug('WhatsApp status', $status);
        }

        foreach ($parsed['messages'] as $msg) {
            if (! $msg['wamid'] || ! $msg['from']) {
                continue;
            }

            [$inbound, $isNew] = $this->logInbound($resolver, $msg);
            if (! $isNew) {
                Log::info('WhatsApp duplicate ignored', ['wamid' => $msg['wamid']]);
                continue;
            }

            $wa->markRead($msg['wamid']);

            $contact      = $inbound->contact;
            $conversation = $contact->conversation;

            if ($conversation && $conversation->state === Conversation::STATE_HUMAN) {
                continue; // handed to a human; logged, bot silent
            }
            if (! Setting::get('bot_enabled', true)) {
                continue;
            }

            $reply = $this->buildReply($engine, $transcription, $inbound, $contact, $msg);

            if ($reply !== null && $reply !== '') {
                $wamid = $wa->sendText($contact->wa_id, $reply);
                $this->logOutbound($contact->id, $reply, $wamid);
            }
        }
    }

    private function buildReply(
        ConversationEngine $engine,
        TranscriptionService $transcription,
        Message $inbound,
        Contact $contact,
        array $msg,
    ): ?string {
        $lang = $contact->locale ?: 'es';

        // Transcribe voice notes first.
        $transcript = null;
        if ($msg['type'] === 'audio' && ! empty($msg['media_id'])) {
            $transcript = $transcription->transcribe($msg['media_id']);
        }

        $decision = InboundRouter::decide($msg['type'], $msg['body'], $transcript);

        if ($decision['mode'] === 'please_type_audio') {
            return Replies::pleaseType($lang, audio: true);
        }
        if ($decision['mode'] === 'please_type') {
            return Replies::pleaseType($lang, audio: false);
        }

        // engine mode: persist the usable text (transcript) onto the log row so
        // the panel + history show what was said, then run the engine.
        if ($transcript && $inbound->exists && $inbound->body !== $transcript) {
            $inbound->update(['body' => $transcript]);
        }

        $history = Message::where('contact_id', $contact->id)
            ->where('wa_message_id', '!=', $msg['wamid'])
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->reverse()
            ->values();

        try {
            return $engine->handle($contact, $decision['text'], $history);
        } catch (\Throwable $e) {
            Log::error('ConversationEngine failure', ['error' => $e->getMessage(), 'contact' => $contact->id]);
            return Replies::faqFallback($lang);
        }
    }

    /**
     * @return array{0: Message, 1: bool}
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
            $existing = Message::where('wa_message_id', $msg['wamid'])->first();
            return [$existing?->loadMissing('contact.conversation') ?? new Message(), false];
        }

        $message->setRelation('contact', $contact);
        return [$message, true];
    }

    private function logOutbound(int $contactId, string $body, ?string $wamid): void
    {
        Message::create([
            'contact_id'    => $contactId,
            'direction'     => Message::DIR_OUT,
            'type'          => 'text',
            'body'          => $body,
            'wa_message_id' => $wamid,
            'sender'        => 'bot',
            'created_at'    => now(),
        ]);
    }
}
