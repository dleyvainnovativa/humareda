<?php

namespace App\Services\WhatsApp;

use App\Enums\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Coexistence history sync (T12-TP Phase 1e).
 *
 * When a number onboards via Embedded Signup, Meta delivers the business's
 * recent WhatsApp-app conversations as `history` webhook events (up to 180 days,
 * in chunks). This walks those events and backfills contacts + messages so the
 * panel shows prior threads from day one, instead of starting empty.
 *
 * It is import-only and idempotent: messages dedupe on wa_message_id, and it
 * never bumps a conversation's 24h window (these are historical, not live).
 */
class HistoryImporter
{
    /**
     * @return int number of messages imported (new rows)
     */
    public function import(array $payload): int
    {
        $imported = 0;

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? null) !== 'history') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $pid   = $value['metadata']['phone_number_id'] ?? null;

                // wa_id -> profile name, when present.
                $names = [];
                foreach ($value['contacts'] ?? [] as $c) {
                    if (isset($c['wa_id'])) {
                        $names[$c['wa_id']] = $c['profile']['name'] ?? null;
                    }
                }

                foreach ($value['history'] ?? [] as $chunk) {
                    foreach ($chunk['threads'] ?? [] as $thread) {
                        $imported += $this->importThread($thread, $pid, $names);
                    }
                }
            }
        }

        if ($imported) {
            Log::info('WhatsApp coexistence history imported', ['messages' => $imported]);
        }

        return $imported;
    }

    private function importThread(array $thread, ?string $pid, array $names): int
    {
        $waId = $thread['id'] ?? ($thread['contact_id'] ?? null);
        if (! $waId) {
            return 0;
        }

        $contact = Contact::firstOrCreate(
            ['channel' => Channel::WhatsApp->value, 'external_id' => $waId],
            ['wa_id' => $waId, 'wa_phone_number_id' => $pid, 'name' => $names[$waId] ?? null],
        );
        if ($pid && $contact->wa_phone_number_id !== $pid) {
            $contact->update(['wa_phone_number_id' => $pid]);
        }

        // Make sure a conversation row exists (idle) — history never flips state.
        $contact->conversation()->firstOrCreate(
            ['contact_id' => $contact->id],
            ['state' => Conversation::STATE_IDLE],
        );

        $count = 0;
        foreach ($thread['messages'] ?? [] as $m) {
            $count += $this->importMessage($contact->id, $waId, $m) ? 1 : 0;
        }

        return $count;
    }

    private function importMessage(int $contactId, string $customerWaId, array $m): bool
    {
        $wamid = $m['id'] ?? null;
        if (! $wamid) {
            return false;
        }
        if (Message::where('wa_message_id', $wamid)->exists()) {
            return false;
        }

        // Direction: Meta marks business-sent history with history_context.from_me;
        // fall back to comparing the sender against the customer's wa_id.
        $fromMe = $m['history_context']['from_me']
            ?? (($m['from'] ?? null) !== null && $m['from'] !== $customerWaId);

        Message::create([
            'contact_id'    => $contactId,
            'direction'     => $fromMe ? Message::DIR_OUT : Message::DIR_IN,
            'type'          => ($m['type'] ?? 'text') === 'text' ? 'text' : ($m['type'] ?? 'text'),
            'body'          => $this->body($m),
            'wa_message_id' => $wamid,
            'sender'        => $fromMe ? 'App WhatsApp' : 'guest',
            'payload'       => $m,
            'created_at'    => isset($m['timestamp']) ? Carbon::createFromTimestamp((int) $m['timestamp']) : now(),
        ]);

        return true;
    }

    private function body(array $m): ?string
    {
        return match ($m['type'] ?? 'text') {
            'text'        => $m['text']['body'] ?? null,
            'button'      => $m['button']['text'] ?? null,
            'interactive' => $m['interactive']['button_reply']['title'] ?? $m['interactive']['list_reply']['title'] ?? null,
            'image'       => $m['image']['caption'] ?? '[imagen]',
            'audio'       => '[audio]',
            'sticker'     => '[sticker]',
            'location'    => '[ubicación]',
            default       => null,
        };
    }
}
