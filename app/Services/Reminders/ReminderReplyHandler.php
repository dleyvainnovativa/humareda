<?php

namespace App\Services\Reminders;

use App\Models\Contact;
use App\Services\Bot\Replies;
use App\Services\Reservations\CancellationService;
use App\Services\Reservations\ReservationLookup;

/**
 * Handles one-word replies to a reminder (CONFIRMO / CANCELO) deterministically,
 * before the LLM — saving a call and keeping the behavior predictable.
 *
 * Only acts when the conversation is idle (ProcessInboundMessage gates this) and
 * the guest has an upcoming reservation. Ambiguous cancels (more than one
 * upcoming booking) fall through to the engine's cancel flow, which lists them.
 */
class ReminderReplyHandler
{
    public function __construct(
        private ReservationLookup $lookup,
        private CancellationService $cancellation,
    ) {}

    /** PURE: 'confirm' | 'cancel' | null */
    public static function classify(string $text): ?string
    {
        $t = mb_strtolower(trim($text));
        $t = strtr($t, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);

        if (preg_match('/\b(confirmo|confirmar|confirmado|confirm|confirmed)\b/u', $t)) {
            return 'confirm';
        }
        if (preg_match('/\b(cancelo|cancela|cancelar|cancel|cancelled|canceled)\b/u', $t)) {
            return 'cancel';
        }
        return null;
    }

    /**
     * Returns a reply string if handled, or null to fall through to the engine.
     */
    public function maybeHandle(Contact $contact, string $text, string $lang): ?string
    {
        $cls = self::classify($text);
        if (! $cls) {
            return null;
        }

        $upcoming = $this->lookup->upcoming($contact);
        if ($upcoming->isEmpty()) {
            return null; // nothing to confirm/cancel — let the engine handle it
        }

        if ($cls === 'confirm') {
            return $lang === 'en'
                ? "Thanks for confirming — see you today! 🔥"
                : "¡Gracias por confirmar! Te esperamos hoy. 🔥";
        }

        // cancel: only auto-cancel when it's unambiguous (exactly one upcoming).
        if ($upcoming->count() > 1) {
            return null; // engine's cancel flow will list them
        }

        $reservation = $upcoming->first();
        $this->cancellation->cancel($reservation);

        return Replies::cancelled([
            'date' => $reservation->reserved_date instanceof \Illuminate\Support\Carbon
                ? $reservation->reserved_date->toDateString() : (string) $reservation->reserved_date,
            'time' => substr((string) $reservation->reserved_time, 0, 5),
        ], $lang);
    }
}
