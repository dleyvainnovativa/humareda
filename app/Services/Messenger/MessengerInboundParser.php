<?php

namespace App\Services\Messenger;

/**
 * Normalizes a Messenger "page" webhook into the same shape the engine already
 * consumes for WhatsApp, so ProcessInboundMessage stays channel-neutral:
 *   wamid -> message id (mid)   from -> sender PSID
 *   type/body -> text or an attachment type marker
 *
 * Non-text attachments keep their type so the router asks the guest to type
 * (social voicenote transcription is a later enhancement); media_id stays null
 * so the WhatsApp transcription path never fires on a Messenger URL.
 */
class MessengerInboundParser
{
    /**
     * @return array{messages: array<int, array>, statuses: array<int, array>}
     */
    public static function parse(array $payload): array
    {
        $messages = [];
        $statuses = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $event) {
                if (isset($event['read']) || isset($event['delivery'])) {
                    $statuses[] = [
                        'psid'  => $event['sender']['id'] ?? null,
                        'event' => isset($event['read']) ? 'read' : 'delivery',
                    ];
                    continue;
                }

                // Only inbound messages; skip postbacks, opt-ins, echoes of our own sends.
                if (! isset($event['message']) || ! empty($event['message']['is_echo'])) {
                    continue;
                }

                $messages[] = self::normalize($event);
            }
        }

        return ['messages' => $messages, 'statuses' => $statuses];
    }

    private static function normalize(array $event): array
    {
        $msg  = $event['message'] ?? [];
        $text = $msg['text'] ?? null;
        $type = 'text';

        if (! $text && ! empty($msg['attachments'])) {
            $type = $msg['attachments'][0]['type'] ?? 'unknown'; // image | audio | video | file
        }

        return [
            'wamid'     => $msg['mid'] ?? null,
            'from'      => $event['sender']['id'] ?? null,   // PSID
            'type'      => $text ? 'text' : $type,
            'body'      => $text,
            'media_id'  => null,                             // URL lives in raw; not transcribed yet
            'name'      => null,                             // enriched lazily via profileName()
            'timestamp' => isset($event['timestamp']) ? (int) $event['timestamp'] : null,
            'raw'       => $event,
        ];
    }
}
