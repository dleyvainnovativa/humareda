<?php

namespace App\Services\Reminders;

/**
 * Builds the reminder content for both channels:
 *  - free-form text (inside the 24h window — free)
 *  - approved utility template components (outside the window)
 *
 * IMPORTANT: the template body must be approved in Meta with 3 body variables
 * in THIS order — {{1}} name, {{2}} time, {{3}} party — matching
 * templateComponents(). If your approved template differs, adjust here.
 */
class ReminderMessage
{
    public static function text(string $lang, string $name, string $time, int $party): string
    {
        return $lang === 'en'
            ? "Hi {$name} 👋 A reminder of your reservation at Humareda Prime today at {$time} for {$party}. "
              . "Reply CONFIRM to keep it or CANCEL to cancel."
            : "¡Hola {$name}! 👋 Te recordamos tu reservación en Humareda Prime hoy a las {$time} para {$party} personas. "
              . "Responde CONFIRMO para confirmar o CANCELO para cancelar.";
    }

    public static function templateComponents(string $name, string $time, int $party): array
    {
        return [[
            'type'       => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $name],
                ['type' => 'text', 'text' => $time],
                ['type' => 'text', 'text' => (string) $party],
            ],
        ]];
    }

    public static function langCode(string $locale): string
    {
        return $locale === 'en' ? 'en_US' : 'es_MX';
    }
}
