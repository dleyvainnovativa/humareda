<?php

namespace App\Services\Bot;

/**
 * Pure decision for how to handle an inbound message by type/content, after
 * any transcription has been attempted. Keeps the hot-path routing testable.
 *
 * Modes:
 *   engine            -> run the ConversationEngine on 'text'
 *   please_type_audio -> we couldn't use the voice note; ask them to type
 *   please_type       -> non-text we don't handle (sticker, location, failed image)
 */
class InboundRouter
{
    /**
     * @return array{mode:string,text:?string}
     */
    public static function decide(string $type, ?string $body, ?string $transcript): array
    {
        $body = $body !== null ? trim($body) : null;
        $body = $body === '' ? null : $body;

        return match ($type) {
            'text' => $body
                ? ['mode' => 'engine', 'text' => $body]
                : ['mode' => 'please_type', 'text' => null],

            'audio' => $transcript && trim($transcript) !== ''
                ? ['mode' => 'engine', 'text' => trim($transcript)]
                : ['mode' => 'please_type_audio', 'text' => null],

            // Image/caption: if the guest wrote a caption, treat it as text.
            'image' => $body
                ? ['mode' => 'engine', 'text' => $body]
                : ['mode' => 'please_type', 'text' => null],

            default => ['mode' => 'please_type', 'text' => null],
        };
    }
}
