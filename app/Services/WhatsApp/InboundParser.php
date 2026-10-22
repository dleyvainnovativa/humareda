<?php

namespace App\Services\WhatsApp;

/**
 * Normalizes a raw Cloud API webhook payload into simple arrays.
 *
 * A webhook body can carry multiple entries/changes. Each "value" block may
 * contain inbound `messages` and/or delivery `statuses`. We only act on
 * messages; statuses (sent/delivered/read receipts) are returned separately
 * so callers can log or ignore them.
 *
 * Coexistence (Tech Provider) adds two more blocks we surface here:
 *   - `message_echoes`  -> messages the business sent from the WhatsApp app
 *                          (staff replying by hand). Drives the bot-quiet
 *                          handoff (field: smb_message_echoes).
 *   - sync events        -> history / smb_app_state_sync / account_update,
 *                          surfaced as `events` for logging + future sync.
 */
class InboundParser
{
    /**
     * @return array{messages: array<int, array>, statuses: array<int, array>, echoes: array<int, array>, events: array<int, array>}
     */
    public static function parse(array $payload): array
    {
        $messages = [];
        $statuses = [];
        $echoes   = [];
        $events   = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = $change['value'] ?? [];
                $field = $change['field'] ?? null;

                // Map wa_id -> profile name for enrichment.
                $names = [];
                foreach ($value['contacts'] ?? [] as $c) {
                    if (isset($c['wa_id'])) {
                        $names[$c['wa_id']] = $c['profile']['name'] ?? null;
                    }
                }

                // Which of our business numbers this event arrived on (multi-number / coexistence).
                $pid = $value['metadata']['phone_number_id'] ?? null;
                foreach ($value['messages'] ?? [] as $m) {
                    $nm = self::normalizeMessage($m, $names);
                    $nm['phone_number_id'] = $pid;
                    $messages[] = $nm;
                }

                foreach ($value['statuses'] ?? [] as $s) {
                    $statuses[] = [
                        'wamid'     => $s['id'] ?? null,
                        'status'    => $s['status'] ?? null,
                        'recipient' => $s['recipient_id'] ?? null,
                    ];
                }

                // Coexistence: outbound messages the business sent from the WhatsApp app.
                foreach ($value['message_echoes'] ?? [] as $e) {
                    $ne = self::normalizeMessage($e, $names);
                    $ne['to']              = $e['to'] ?? null;   // the customer this echo went to
                    $ne['phone_number_id'] = $pid;
                    $echoes[] = $ne;
                }

                // Coexistence: onboarding history + state sync + number status updates.
                if (in_array($field, ['history', 'smb_app_state_sync', 'account_update'], true)) {
                    $events[] = ['field' => $field, 'phone_number_id' => $pid];
                }
            }
        }

        return ['messages' => $messages, 'statuses' => $statuses, 'echoes' => $echoes, 'events' => $events];
    }

    /**
     * Reduce a raw message object to the fields the pipe cares about.
     * Text is extracted for text/interactive/button; other types keep a
     * type marker and (for media) an id for later download (T7).
     */
    private static function normalizeMessage(array $m, array $names): array
    {
        $type = $m['type'] ?? 'unknown';
        $from = $m['from'] ?? null;

        $body     = null;
        $mediaId  = null;

        switch ($type) {
            case 'text':
                $body = $m['text']['body'] ?? null;
                break;
            case 'button':
                $body = $m['button']['text'] ?? null;
                break;
            case 'interactive':
                $body = $m['interactive']['button_reply']['title']
                    ?? $m['interactive']['list_reply']['title']
                    ?? null;
                break;
            case 'audio':
                $mediaId = $m['audio']['id'] ?? null;
                break;
            case 'image':
                $mediaId = $m['image']['id'] ?? null;
                $body    = $m['image']['caption'] ?? null;
                break;
            case 'sticker':
                $mediaId = $m['sticker']['id'] ?? null;
                break;
            case 'location':
                $body = 'location';
                break;
        }

        return [
            'wamid'     => $m['id'] ?? null,
            'from'      => $from,
            'type'      => $type,
            'body'      => $body,
            'media_id'  => $mediaId,
            'name'      => $names[$from] ?? null,
            'timestamp' => isset($m['timestamp']) ? (int) $m['timestamp'] : null,
            'raw'       => $m,
        ];
    }
}
