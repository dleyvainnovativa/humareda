<?php

namespace App\Services\Messenger;

/**
 * Normalizes a Messenger "page" webhook into the same shape the engine already
 * consumes for WhatsApp, so ProcessInboundMessage stays channel-neutral:
 *   wamid -> message id (mid)   from -> sender PSID
 *   type/body -> text or an attachment type marker
 *
 * Audio attachments carry their URL in media_id so the (channel-aware) transcriber
 * can fetch and transcribe voice notes; other non-text attachments keep their type
 * with media_id null, so the router simply asks the guest to type.
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
        $msg      = $event['message'] ?? [];
        $text     = $msg['text'] ?? null;
        $type     = 'text';
        $mediaUrl = null;

        if (! $text && ! empty($msg['attachments'])) {
            $att  = $msg['attachments'][0];
            $type = $att['type'] ?? 'unknown';               // image | audio | video | file
            if ($type === 'audio') {
                $mediaUrl = $att['payload']['url'] ?? null;  // voice note -> transcribed via downloadMedia(url)
            }
        }

        return [
            'wamid'     => $msg['mid'] ?? null,
            'from'      => $event['sender']['id'] ?? null,   // PSID
            'type'      => $text ? 'text' : $type,
            'body'      => $text,
            'media_id'  => $mediaUrl,                         // audio URL for transcription; null otherwise
            'name'      => null,                             // enriched lazily via profileName()
            'timestamp' => isset($event['timestamp']) ? (int) $event['timestamp'] : null,
            'raw'       => $event,
        ];
    }
}
