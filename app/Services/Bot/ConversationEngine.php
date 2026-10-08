<?php

namespace App\Services\Bot;

use App\Models\Contact;
use App\Models\Conversation;
use App\Services\AI\TurnInterpreter;
use App\Services\Availability\AvailabilityResult;
use App\Services\Availability\BookingService;
use Illuminate\Support\Collection;

/**
 * The brain. Interprets the guest's message (LLM) then drives a deterministic
 * state machine: greeting -> slot-filling -> availability -> confirm -> book.
 * The LLM understands; this class (and BookingService) decide and write.
 *
 * States (on Conversation): idle, booking, confirming, human.
 * Cancel/modify/check are recognized and handed off here; T5 implements them.
 *
 * Returns the reply text to send, or null to stay silent (already-human).
 */
class ConversationEngine
{
    public function __construct(
        private TurnInterpreter $interpreter,
        private BookingService $booking,
    ) {}

    public function handle(Contact $contact, string $text, Collection $history): ?string
    {
        $conversation = $contact->conversation;
        $ctx    = $conversation->context ?? [];
        $slots  = SlotFiller::merge(SlotFiller::empty(), $ctx['slots'] ?? []);
        $lang   = $contact->locale ?: 'es';

        // Fast-path handoff before spending an LLM call.
        if (Affirmation::wantsHuman($text)) {
            return $this->goHuman($contact, $conversation, $lang);
        }

        $interp = $this->interpreter->interpret($text, $history, $slots);
        $lang   = $interp['language'];
        if ($contact->locale !== $lang) {
            $contact->locale = $lang;
            $contact->save();
        }

        if ($interp['intent'] === 'talk_to_human') {
            return $this->goHuman($contact, $conversation, $lang);
        }

        // Confirmation step is special: interpret yes / no / change.
        if ($conversation->state === Conversation::STATE_CONFIRMING) {
            return $this->handleConfirming($contact, $conversation, $slots, $interp, $text, $lang);
        }

        // Non-booking intents.
        switch ($interp['intent']) {
            case 'faq':
                return $this->handleFaq($conversation, $slots, $interp, $lang);

            case 'cancel_reservation':
            case 'modify_reservation':
            case 'check_reservation':
                // T5 implements these; hand to a person for now.
                return $this->goHuman($contact, $conversation, $lang);

            case 'greeting':
                if (! array_filter($slots)) {
                    $this->persist($conversation, Conversation::STATE_IDLE, $slots);
                    return Replies::greeting($lang);
                }
                // greeting carrying details falls through to booking.
                break;
        }

        // Booking path (make_reservation, or continuing a booking, or any
        // message that carried booking slots).
        $slots = SlotFiller::merge($slots, $interp['slots']);

        if ($miss = SlotFiller::missing($slots)) {
            $this->persist($conversation, Conversation::STATE_BOOKING, $slots);
            // If nothing at all was provided and intent is vague, nudge softly.
            if (! array_filter($slots) && ! in_array($interp['intent'], ['make_reservation'], true)) {
                return Replies::fallback($lang);
            }
            return SlotFiller::promptFor($miss, $lang);
        }

        // All four slots present -> check availability.
        $result = $this->booking->check($slots['date'], $slots['time'], (int) $slots['party_size']);
        return $this->applyAvailability($contact, $conversation, $slots, $result, $lang);
    }

    // ----------------------------------------------------------------------

    private function handleConfirming(
        Contact $contact,
        Conversation $conversation,
        array $slots,
        array $interp,
        string $text,
        string $lang,
    ): ?string {
        // Did the guest change something? Re-evaluate against new slots.
        $merged = SlotFiller::merge($slots, $interp['slots']);
        $changed = $merged !== $slots;

        if ($changed) {
            if ($miss = SlotFiller::missing($merged)) {
                $this->persist($conversation, Conversation::STATE_BOOKING, $merged);
                return SlotFiller::promptFor($miss, $lang);
            }
            $result = $this->booking->check($merged['date'], $merged['time'], (int) $merged['party_size']);
            return $this->applyAvailability($contact, $conversation, $merged, $result, $lang);
        }

        if (Affirmation::isYes($text)) {
            return $this->doBook($contact, $conversation, $slots, $lang);
        }

        if (Affirmation::isNo($text)) {
            $this->persist($conversation, Conversation::STATE_BOOKING, $slots);
            return $lang === 'en'
                ? "No problem — what would you like to change? (day, time, people or name)"
                : "Sin problema — ¿qué te gustaría cambiar? (día, hora, personas o nombre)";
        }

        // Unclear: re-show the summary.
        return Replies::confirmSummary($slots, $lang);
    }

    private function doBook(Contact $contact, Conversation $conversation, array $slots, string $lang): string
    {
        $outcome = $this->booking->book(
            $contact->id, $slots['date'], $slots['time'], (int) $slots['party_size'], $slots['name'], null, 'bot'
        );

        /** @var AvailabilityResult $result */
        $result = $outcome['result'];

        if ($outcome['reservation']) {
            // T9: schedule the same-day reminder for $outcome['reservation'] here.
            $r = $outcome['reservation'];
            $display = [
                'date'       => $r->reserved_date instanceof \Illuminate\Support\Carbon
                    ? $r->reserved_date->toDateString() : (string) $r->reserved_date,
                'time'       => substr((string) $r->reserved_time, 0, 5),
                'party_size' => (int) $r->party_size,
                'name'       => $r->name,
            ];
            $this->persist($conversation, Conversation::STATE_IDLE, SlotFiller::empty());
            return Replies::confirmed($display, $lang);
        }

        // Lost a race, or slot filled between confirm and write.
        return $this->applyAvailability($contact, $conversation, $slots, $result, $lang, raceOnFull: true);
    }

    /**
     * Translate an availability result into a reply + state transition.
     * Extracted so the branching is unit-testable (docs/verify_t4.php).
     */
    private function applyAvailability(
        Contact $contact,
        Conversation $conversation,
        array $slots,
        AvailabilityResult $result,
        string $lang,
        bool $raceOnFull = false,
    ): ?string {
        $decision = self::mapAvailabilityOutcome($result, $lang, $slots['name'] ?? null, $raceOnFull);

        // Apply slot clears (e.g. bad time) before persisting.
        foreach ($decision['clear'] as $field) {
            $slots[$field] = null;
        }

        if ($decision['state'] === Conversation::STATE_HUMAN) {
            $this->persist($conversation, Conversation::STATE_HUMAN, $slots);
            // T6: notify staff (email + panel) here.
            return $decision['reply'];
        }

        $this->persist($conversation, $decision['state'], $slots);
        return $decision['reply'];
    }

    /**
     * PURE mapping: (AvailabilityResult, lang) -> reply/state/clear.
     * @return array{reply:string,state:string,clear:string[]}
     */
    public static function mapAvailabilityOutcome(AvailabilityResult $result, string $lang, ?string $name = null, bool $raceOnFull = false): array
    {
        return match ($result->status) {
            'available' => [
                'reply' => Replies::confirmSummary(
                    ['date' => $result->date, 'time' => $result->time, 'party_size' => $result->party, 'name' => $name ?? ''],
                    $lang
                ),
                'state' => Conversation::STATE_CONFIRMING,
                'clear' => [],
            ],
            'full' => [
                'reply' => $raceOnFull
                    ? Replies::bookingRace($lang)
                    : ($result->alternatives ? Replies::alternatives($result->alternatives, $lang) : Replies::noAlternatives($lang)),
                'state' => Conversation::STATE_BOOKING,
                'clear' => ['time'],
            ],
            'outside_hours' => [
                'reply' => Replies::outsideHours($lang, $result->alternatives),
                'state' => Conversation::STATE_BOOKING,
                'clear' => ['time'],
            ],
            'closed' => [
                'reply' => Replies::closed($lang),
                'state' => Conversation::STATE_BOOKING,
                'clear' => ['date'],
            ],
            'needs_human' => [
                'reply' => Replies::bigParty($lang),
                'state' => Conversation::STATE_HUMAN,
                'clear' => [],
            ],
            'invalid' => [
                'reply' => Replies::invalidParty($lang),
                'state' => Conversation::STATE_BOOKING,
                'clear' => ['party_size'],
            ],
            default => [
                'reply' => Replies::fallback($lang),
                'state' => Conversation::STATE_BOOKING,
                'clear' => [],
            ],
        };
    }

    private function handleFaq(Conversation $conversation, array $slots, array $interp, string $lang): string
    {
        $answer = $interp['faq_answer'] ?? null;
        $reply  = $answer ?: Replies::faqFallback($lang);

        // If we were mid-booking, resume by asking the next missing slot.
        if ($conversation->state === Conversation::STATE_BOOKING && ($miss = SlotFiller::missing($slots))) {
            $reply .= "\n\n" . SlotFiller::promptFor($miss, $lang);
        }

        return $reply;
    }

    private function goHuman(Contact $contact, Conversation $conversation, string $lang): string
    {
        $this->persist($conversation, Conversation::STATE_HUMAN, $conversation->context['slots'] ?? SlotFiller::empty());
        // T6: notify staff (email + panel flag) here.
        return Replies::handoff($lang);
    }

    private function persist(Conversation $conversation, string $state, array $slots): void
    {
        $conversation->state   = $state;
        $conversation->context = ['slots' => $slots];
        if ($conversation->isDirty('state')) {
            $conversation->state_changed_at = now();
        }
        $conversation->save();
    }
}
