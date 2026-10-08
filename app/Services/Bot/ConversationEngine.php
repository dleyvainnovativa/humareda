<?php

namespace App\Services\Bot;

use App\Events\ConversationHandedOff;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Reservation;
use App\Services\AI\TurnInterpreter;
use App\Services\Availability\AvailabilityResult;
use App\Services\Availability\BookingService;
use App\Services\Reservations\CancellationService;
use App\Services\Reservations\ReservationLookup;
use App\Services\Reservations\ReservationSelector;
use Illuminate\Support\Collection;

/**
 * The brain. LLM understands; this state machine + services decide and write.
 * (T5 adds cancel / modify / status flows on top of the T4 booking flow —
 * this file replaces the T4 version.)
 *
 * States: idle, booking, confirming, cancelling, modifying, human.
 * Context: ['slots'=>..., 'flow'=>?, 'stage'=>?, 'target_id'=>?,
 *           'candidates'=>[...], 'mod_slots'=>...]
 */
class ConversationEngine
{
    public function __construct(
        private TurnInterpreter $interpreter,
        private BookingService $booking,
        private ReservationLookup $lookup,
        private CancellationService $cancellation,
    ) {}

    public function handle(Contact $contact, string $text, Collection $history): ?string
    {
        $conversation = $contact->conversation;
        $ctx   = $conversation->context ?? [];
        $slots = SlotFiller::merge(SlotFiller::empty(), $ctx['slots'] ?? []);
        $lang  = $contact->locale ?: 'es';

        if (Affirmation::wantsHuman($text)) {
            return $this->goHuman($contact, $conversation, $ctx, $lang);
        }

        $interp = $this->interpreter->interpret($text, $history, $slots);
        $lang   = $interp['language'];
        if ($contact->locale !== $lang) {
            $contact->locale = $lang;
            $contact->save();
        }

        if ($interp['intent'] === 'talk_to_human') {
            return $this->goHuman($contact, $conversation, $ctx, $lang);
        }

        // Mid-flow states take priority over a fresh intent.
        switch ($conversation->state) {
            case Conversation::STATE_CONFIRMING:
                return $this->handleConfirming($contact, $conversation, $slots, $interp, $text, $lang);
            case Conversation::STATE_CANCELLING:
                return $this->handleCancelling($contact, $conversation, $ctx, $interp, $text, $lang);
            case Conversation::STATE_MODIFYING:
                return $this->handleModifying($contact, $conversation, $ctx, $interp, $text, $lang);
        }

        // Fresh intents.
        switch ($interp['intent']) {
            case 'faq':
                return $this->handleFaq($conversation, $slots, $interp, $lang);
            case 'check_reservation':
                return $this->handleCheck($contact, $conversation, $lang);
            case 'cancel_reservation':
                return $this->startCancel($contact, $conversation, $lang);
            case 'modify_reservation':
                return $this->startModify($contact, $conversation, $lang);
            case 'greeting':
                if (! array_filter($slots)) {
                    $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => $slots]);
                    return Replies::greeting($lang);
                }
                break;
        }

        // Booking path.
        return $this->handleBooking($contact, $conversation, $slots, $interp, $lang);
    }

    // -------------------------------------------------------------- booking

    private function handleBooking(Contact $contact, Conversation $conversation, array $slots, array $interp, string $lang): ?string
    {
        $slots = SlotFiller::merge($slots, $interp['slots']);

        if ($miss = SlotFiller::missing($slots)) {
            $this->persist($conversation, Conversation::STATE_BOOKING, ['slots' => $slots]);
            if (! array_filter($slots) && $interp['intent'] !== 'make_reservation') {
                return Replies::fallback($lang);
            }
            return SlotFiller::promptFor($miss, $lang);
        }

        $result = $this->booking->check($slots['date'], $slots['time'], (int) $slots['party_size']);
        return $this->applyAvailability($contact, $conversation, $slots, $result, $lang);
    }

    private function handleConfirming(Contact $contact, Conversation $conversation, array $slots, array $interp, string $text, string $lang): ?string
    {
        $merged  = SlotFiller::merge($slots, $interp['slots']);
        if ($merged !== $slots) {
            if ($miss = SlotFiller::missing($merged)) {
                $this->persist($conversation, Conversation::STATE_BOOKING, ['slots' => $merged]);
                return SlotFiller::promptFor($miss, $lang);
            }
            $result = $this->booking->check($merged['date'], $merged['time'], (int) $merged['party_size']);
            return $this->applyAvailability($contact, $conversation, $merged, $result, $lang);
        }

        if (Affirmation::isYes($text)) {
            return $this->doBook($contact, $conversation, $slots, $lang);
        }
        if (Affirmation::isNo($text)) {
            $this->persist($conversation, Conversation::STATE_BOOKING, ['slots' => $slots]);
            return $lang === 'en'
                ? "No problem — what would you like to change? (day, time, people or name)"
                : "Sin problema — ¿qué te gustaría cambiar? (día, hora, personas o nombre)";
        }
        return Replies::confirmSummary($slots, $lang);
    }

    private function doBook(Contact $contact, Conversation $conversation, array $slots, string $lang): string
    {
        $outcome = $this->booking->book($contact->id, $slots['date'], $slots['time'], (int) $slots['party_size'], $slots['name'], null, 'bot');
        if ($outcome['reservation']) {
            // T9: schedule same-day reminder here.
            $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
            return Replies::confirmed($this->display($outcome['reservation']), $lang);
        }
        return $this->applyAvailability($contact, $conversation, $slots, $outcome['result'], $lang, raceOnFull: true);
    }

    private function applyAvailability(Contact $contact, Conversation $conversation, array $slots, AvailabilityResult $result, string $lang, bool $raceOnFull = false): ?string
    {
        $decision = self::mapAvailabilityOutcome($result, $lang, $slots['name'] ?? null, $raceOnFull);
        foreach ($decision['clear'] as $field) {
            $slots[$field] = null;
        }
        if ($decision['state'] === Conversation::STATE_HUMAN) {
            $this->enterHuman($contact, $conversation, ['slots' => $slots], 'big_party');
            return $decision['reply'];
        }
        $this->persist($conversation, $decision['state'], ['slots' => $slots]);
        return $decision['reply'];
    }

    /** @return array{reply:string,state:string,clear:string[]} */
    public static function mapAvailabilityOutcome(AvailabilityResult $result, string $lang, ?string $name = null, bool $raceOnFull = false): array
    {
        return match ($result->status) {
            'available' => [
                'reply' => Replies::confirmSummary(['date' => $result->date, 'time' => $result->time, 'party_size' => $result->party, 'name' => $name ?? ''], $lang),
                'state' => Conversation::STATE_CONFIRMING, 'clear' => [],
            ],
            'full' => [
                'reply' => $raceOnFull ? Replies::bookingRace($lang)
                    : ($result->alternatives ? Replies::alternatives($result->alternatives, $lang) : Replies::noAlternatives($lang)),
                'state' => Conversation::STATE_BOOKING, 'clear' => ['time'],
            ],
            'outside_hours' => ['reply' => Replies::outsideHours($lang, $result->alternatives), 'state' => Conversation::STATE_BOOKING, 'clear' => ['time']],
            'closed'        => ['reply' => Replies::closed($lang), 'state' => Conversation::STATE_BOOKING, 'clear' => ['date']],
            'needs_human'   => ['reply' => Replies::bigParty($lang), 'state' => Conversation::STATE_HUMAN, 'clear' => []],
            'invalid'       => ['reply' => Replies::invalidParty($lang), 'state' => Conversation::STATE_BOOKING, 'clear' => ['party_size']],
            default         => ['reply' => Replies::fallback($lang), 'state' => Conversation::STATE_BOOKING, 'clear' => []],
        };
    }

    // --------------------------------------------------------------- status

    private function handleCheck(Contact $contact, Conversation $conversation, string $lang): string
    {
        $summaries = $this->lookup->upcomingSummaries($contact);
        $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);

        if (! $summaries) {
            return Replies::noReservation($lang);
        }
        if (count($summaries) === 1) {
            return Replies::readback($summaries[0], $lang);
        }
        return Replies::listReservations($summaries, $lang, 'check');
    }

    // --------------------------------------------------------------- cancel

    private function startCancel(Contact $contact, Conversation $conversation, string $lang): string
    {
        $summaries = $this->lookup->upcomingSummaries($contact);
        if (! $summaries) {
            $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
            return Replies::noReservation($lang);
        }
        if (count($summaries) === 1) {
            $this->persist($conversation, Conversation::STATE_CANCELLING, [
                'flow' => 'cancel', 'stage' => 'confirm', 'target_id' => $summaries[0]['id'], 'candidates' => $summaries,
            ]);
            return Replies::confirmCancel($summaries[0], $lang);
        }
        $this->persist($conversation, Conversation::STATE_CANCELLING, [
            'flow' => 'cancel', 'stage' => 'select', 'candidates' => $summaries,
        ]);
        return Replies::listReservations($summaries, $lang, 'cancel');
    }

    private function handleCancelling(Contact $contact, Conversation $conversation, array $ctx, array $interp, string $text, string $lang): string
    {
        $cands = $ctx['candidates'] ?? [];
        $stage = $ctx['stage'] ?? 'confirm';

        if ($stage === 'select') {
            $id = ReservationSelector::pick($cands, $text, $interp['slots']);
            if (! $id) {
                return Replies::selectionUnclear($lang);
            }
            $ctx['target_id'] = $id;
            $ctx['stage'] = 'confirm';
            $this->persist($conversation, Conversation::STATE_CANCELLING, $ctx);
            return Replies::confirmCancel($this->summaryById($cands, $id), $lang);
        }

        // confirm stage
        $summary = $this->summaryById($cands, $ctx['target_id'] ?? null);
        if (Affirmation::isYes($text)) {
            $reservation = Reservation::find($ctx['target_id'] ?? 0);
            if ($reservation && $this->cancellation->cancel($reservation)) {
                $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
                return Replies::cancelled($summary ?? $this->display($reservation), $lang);
            }
            $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
            return Replies::noReservation($lang);
        }
        if (Affirmation::isNo($text)) {
            $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
            return Replies::cancelKept($lang);
        }
        return $summary ? Replies::confirmCancel($summary, $lang) : Replies::selectionUnclear($lang);
    }

    // --------------------------------------------------------------- modify

    private function startModify(Contact $contact, Conversation $conversation, string $lang): string
    {
        $summaries = $this->lookup->upcomingSummaries($contact);
        if (! $summaries) {
            $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
            return Replies::noReservation($lang);
        }
        if (count($summaries) === 1) {
            $s = $summaries[0];
            $this->persist($conversation, Conversation::STATE_MODIFYING, [
                'flow' => 'modify', 'stage' => 'collect', 'target_id' => $s['id'],
                'candidates' => $summaries, 'mod_slots' => $this->slotsFromSummary($s),
            ]);
            return Replies::askWhatToChange($s, $lang);
        }
        $this->persist($conversation, Conversation::STATE_MODIFYING, [
            'flow' => 'modify', 'stage' => 'select', 'candidates' => $summaries,
        ]);
        return Replies::listReservations($summaries, $lang, 'modify');
    }

    private function handleModifying(Contact $contact, Conversation $conversation, array $ctx, array $interp, string $text, string $lang): ?string
    {
        $cands = $ctx['candidates'] ?? [];
        $stage = $ctx['stage'] ?? 'collect';

        if ($stage === 'select') {
            $id = ReservationSelector::pick($cands, $text, $interp['slots']);
            if (! $id) {
                return Replies::selectionUnclear($lang);
            }
            $s = $this->summaryById($cands, $id);
            $ctx = array_merge($ctx, ['target_id' => $id, 'stage' => 'collect', 'mod_slots' => $this->slotsFromSummary($s)]);
            $this->persist($conversation, Conversation::STATE_MODIFYING, $ctx);
            return Replies::askWhatToChange($s, $lang);
        }

        $reservation = Reservation::find($ctx['target_id'] ?? 0);
        if (! $reservation) {
            $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
            return Replies::noReservation($lang);
        }

        $mod = SlotFiller::merge($ctx['mod_slots'] ?? SlotFiller::empty(), $interp['slots']);

        if ($stage === 'confirm_mod') {
            // Changed again? re-evaluate. Yes? apply. No? keep.
            if ($mod !== ($ctx['mod_slots'] ?? [])) {
                return $this->evaluateModify($contact, $conversation, $ctx, $reservation, $mod, $lang);
            }
            if (Affirmation::isYes($text)) {
                return $this->applyModify($conversation, $reservation, $mod, $lang);
            }
            if (Affirmation::isNo($text)) {
                $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
                return Replies::modifyKept($lang);
            }
            return Replies::confirmModify($mod, $lang);
        }

        // collect stage
        if ($mod === ($ctx['mod_slots'] ?? []) && ! array_filter($interp['slots'])) {
            return Replies::askWhatToChange($this->summaryById($cands, $ctx['target_id']) ?? $this->display($reservation), $lang);
        }
        return $this->evaluateModify($contact, $conversation, $ctx, $reservation, $mod, $lang);
    }

    private function evaluateModify(Contact $contact, Conversation $conversation, array $ctx, Reservation $reservation, array $mod, string $lang): string
    {
        if ($miss = SlotFiller::missing($mod)) {
            $ctx = array_merge($ctx, ['stage' => 'collect', 'mod_slots' => $mod]);
            $this->persist($conversation, Conversation::STATE_MODIFYING, $ctx);
            return SlotFiller::promptFor($miss, $lang);
        }

        $result = $this->booking->checkModify($reservation, $mod['date'], $mod['time'], (int) $mod['party_size']);
        $decision = self::mapModifyOutcome($result, $lang, $mod['name'] ?? null);

        foreach ($decision['clear'] as $f) {
            $mod[$f] = null;
        }

        if ($decision['human']) {
            $this->enterHuman($contact, $conversation, $ctx, 'big_party');
            return $decision['reply'];
        }

        $ctx = array_merge($ctx, ['stage' => $decision['stage'], 'mod_slots' => $mod]);
        $this->persist($conversation, Conversation::STATE_MODIFYING, $ctx);
        return $decision['reply'];
    }

    private function applyModify(Conversation $conversation, Reservation $reservation, array $mod, string $lang): string
    {
        $outcome = $this->booking->modify($reservation, $mod['date'], $mod['time'], (int) $mod['party_size'], $mod['name'] ?? null);
        if ($outcome['reservation']) {
            // T9: reschedule same-day reminder here.
            $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
            return Replies::modified($this->display($outcome['reservation']), $lang);
        }
        // Race/full at write time — offer alternatives, stay in collect.
        $decision = self::mapModifyOutcome($outcome['result'], $lang, $mod['name'] ?? null);
        foreach ($decision['clear'] as $f) {
            $mod[$f] = null;
        }
        $this->persist($conversation, Conversation::STATE_MODIFYING, array_merge(
            ['flow' => 'modify', 'target_id' => $reservation->id, 'candidates' => []],
            ['stage' => 'collect', 'mod_slots' => $mod],
        ));
        return $decision['reply'];
    }

    /** @return array{reply:string,stage:string,clear:string[],human:bool} */
    public static function mapModifyOutcome(AvailabilityResult $result, string $lang, ?string $name = null): array
    {
        return match ($result->status) {
            'available' => [
                'reply' => Replies::confirmModify(['date' => $result->date, 'time' => $result->time, 'party_size' => $result->party, 'name' => $name ?? ''], $lang),
                'stage' => 'confirm_mod', 'clear' => [], 'human' => false,
            ],
            'full' => [
                'reply' => $result->alternatives ? Replies::alternatives($result->alternatives, $lang) : Replies::noAlternatives($lang),
                'stage' => 'collect', 'clear' => ['time'], 'human' => false,
            ],
            'outside_hours' => ['reply' => Replies::outsideHours($lang, $result->alternatives), 'stage' => 'collect', 'clear' => ['time'], 'human' => false],
            'closed'        => ['reply' => Replies::closed($lang), 'stage' => 'collect', 'clear' => ['date'], 'human' => false],
            'needs_human'   => ['reply' => Replies::bigParty($lang), 'stage' => 'collect', 'clear' => [], 'human' => true],
            'invalid'       => ['reply' => Replies::invalidParty($lang), 'stage' => 'collect', 'clear' => ['party_size'], 'human' => false],
            default         => ['reply' => Replies::fallback($lang), 'stage' => 'collect', 'clear' => [], 'human' => false],
        };
    }

    // ------------------------------------------------------------- faq/human

    private function handleFaq(Conversation $conversation, array $slots, array $interp, string $lang): string
    {
        $reply = $interp['faq_answer'] ?: Replies::faqFallback($lang);
        if ($conversation->state === Conversation::STATE_BOOKING && ($miss = SlotFiller::missing($slots))) {
            $reply .= "\n\n" . SlotFiller::promptFor($miss, $lang);
        }
        return $reply;
    }

    private function goHuman(Contact $contact, Conversation $conversation, array $ctx, string $lang): string
    {
        $this->enterHuman($contact, $conversation, $ctx, 'guest_request');
        return Replies::handoff($lang);
    }

    /**
     * Transition a conversation to human control and notify staff (once).
     * Fires ConversationHandedOff only on the idle->human edge so re-entering
     * the flow doesn't spam staff.
     */
    private function enterHuman(Contact $contact, Conversation $conversation, array $ctx, string $reason): void
    {
        $wasHuman = $conversation->state === Conversation::STATE_HUMAN;
        $this->persist($conversation, Conversation::STATE_HUMAN, $ctx);

        if (! $wasHuman) {
            event(new ConversationHandedOff(
                $conversation->id, $contact->id, $reason, $contact->wa_id, $contact->name,
            ));
        }
    }

    // --------------------------------------------------------------- helpers

    private function persist(Conversation $conversation, string $state, array $context): void
    {
        $wasState = $conversation->state;
        $conversation->state   = $state;
        $conversation->context = $context;
        if ($wasState !== $state) {
            $conversation->state_changed_at = now();
        }
        $conversation->save();
    }

    private function display(Reservation $r): array
    {
        return [
            'date'       => $r->reserved_date instanceof \Illuminate\Support\Carbon ? $r->reserved_date->toDateString() : (string) $r->reserved_date,
            'time'       => substr((string) $r->reserved_time, 0, 5),
            'party'      => (int) $r->party_size,
            'party_size' => (int) $r->party_size,
            'name'       => (string) $r->name,
        ];
    }

    private function slotsFromSummary(array $s): array
    {
        return ['date' => $s['date'], 'time' => $s['time'], 'party_size' => $s['party'], 'name' => $s['name']];
    }

    private function summaryById(array $cands, ?int $id): ?array
    {
        foreach ($cands as $c) {
            if (($c['id'] ?? null) === $id) {
                return $c;
            }
        }
        return null;
    }
}
