<?php

namespace App\Services\Bot;

use App\Events\ConversationHandedOff;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Reservation;
use App\Services\AI\TurnInterpreter;
use App\Services\Reservations\CancellationService;
use App\Services\Reservations\RequestService;
use App\Services\Reservations\ReservationLookup;
use App\Services\Reservations\ReservationSelector;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The brain (T11 model: capture a request -> human authorizes).
 *
 * The LLM understands; this state machine collects the 5 fields, confirms them,
 * and submits a PENDING request (no availability gate, no covers held). Staff
 * authorize from the panel. Cancel is instant; modify re-enters review.
 *
 * States: idle, booking, confirming, cancelling, modifying, human.
 */
class ConversationEngine
{
    public function __construct(
        private TurnInterpreter $interpreter,
        private RequestService $requests,
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

        switch ($conversation->state) {
            case Conversation::STATE_CONFIRMING:
                return $this->handleConfirming($contact, $conversation, $slots, $interp, $text, $lang);
            case Conversation::STATE_CANCELLING:
                return $this->handleCancelling($conversation, $ctx, $interp, $text, $lang);
            case Conversation::STATE_MODIFYING:
                return $this->handleModifying($conversation, $ctx, $interp, $text, $lang);
        }

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

        return $this->handleBooking($conversation, $slots, $interp, $lang);
    }

    // -------------------------------------------------------------- request

    private function handleBooking(Conversation $conversation, array $slots, array $interp, string $lang): string
    {
        $merged = SlotFiller::merge($slots, $interp['slots']);

        // Nothing yet + clear reservation intent -> show the full form once.
        if (! array_filter($merged) && $interp['intent'] === 'make_reservation') {
            $this->persist($conversation, Conversation::STATE_BOOKING, ['slots' => $merged]);
            return Replies::requestForm($lang);
        }

        if ($past = $this->pastDate($merged['date'] ?? null)) {
            $merged['date'] = null;
            $this->persist($conversation, Conversation::STATE_BOOKING, ['slots' => $merged]);
            return Replies::pastDate($lang);
        }

        if ($miss = SlotFiller::missing($merged)) {
            $this->persist($conversation, Conversation::STATE_BOOKING, ['slots' => $merged]);
            if (! array_filter($merged) && $interp['intent'] !== 'make_reservation') {
                return Replies::fallback($lang);
            }
            return SlotFiller::promptFor($miss, $lang);
        }

        $this->persist($conversation, Conversation::STATE_CONFIRMING, ['slots' => $merged]);
        return Replies::confirmSummary($merged, $lang);
    }

    private function handleConfirming(Contact $contact, Conversation $conversation, array $slots, array $interp, string $text, string $lang): string
    {
        $merged = SlotFiller::merge($slots, $interp['slots']);

        if ($merged !== $slots) {
            if ($this->pastDate($merged['date'] ?? null)) {
                $merged['date'] = null;
                $this->persist($conversation, Conversation::STATE_BOOKING, ['slots' => $merged]);
                return Replies::pastDate($lang);
            }
            if ($miss = SlotFiller::missing($merged)) {
                $this->persist($conversation, Conversation::STATE_BOOKING, ['slots' => $merged]);
                return SlotFiller::promptFor($miss, $lang);
            }
            $this->persist($conversation, Conversation::STATE_CONFIRMING, ['slots' => $merged]);
            return Replies::confirmSummary($merged, $lang);
        }

        if (Affirmation::isYes($text)) {
            $this->requests->submitNew($contact, $merged);
            $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
            return Replies::inReview($lang);
        }
        if (Affirmation::isNo($text)) {
            $this->persist($conversation, Conversation::STATE_BOOKING, ['slots' => $merged]);
            return Replies::changeWhat($lang);
        }
        return Replies::confirmSummary($merged, $lang);
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
        $this->persist($conversation, Conversation::STATE_CANCELLING, ['flow' => 'cancel', 'stage' => 'select', 'candidates' => $summaries]);
        return Replies::listReservations($summaries, $lang, 'cancel');
    }

    private function handleCancelling(Conversation $conversation, array $ctx, array $interp, string $text, string $lang): string
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

        $summary = $this->summaryById($cands, $ctx['target_id'] ?? null);
        if (Affirmation::isYes($text)) {
            $reservation = Reservation::find($ctx['target_id'] ?? 0);
            if ($reservation) {
                $this->cancellation->cancel($reservation);
            }
            $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
            return $summary ? Replies::cancelled($summary, $lang) : Replies::noReservation($lang);
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
                'candidates' => $summaries, 'mod_slots' => $this->slotsFromReservation($s['id']),
            ]);
            return Replies::askWhatToChange($s, $lang);
        }
        $this->persist($conversation, Conversation::STATE_MODIFYING, ['flow' => 'modify', 'stage' => 'select', 'candidates' => $summaries]);
        return Replies::listReservations($summaries, $lang, 'modify');
    }

    private function handleModifying(Conversation $conversation, array $ctx, array $interp, string $text, string $lang): string
    {
        $cands = $ctx['candidates'] ?? [];
        $stage = $ctx['stage'] ?? 'collect';

        if ($stage === 'select') {
            $id = ReservationSelector::pick($cands, $text, $interp['slots']);
            if (! $id) {
                return Replies::selectionUnclear($lang);
            }
            $ctx = array_merge($ctx, ['target_id' => $id, 'stage' => 'collect', 'mod_slots' => $this->slotsFromReservation($id)]);
            $this->persist($conversation, Conversation::STATE_MODIFYING, $ctx);
            return Replies::askWhatToChange($this->summaryById($cands, $id), $lang);
        }

        $reservation = Reservation::find($ctx['target_id'] ?? 0);
        if (! $reservation) {
            $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
            return Replies::noReservation($lang);
        }

        $mod = SlotFiller::merge($ctx['mod_slots'] ?? SlotFiller::empty(), $interp['slots']);

        if ($stage === 'confirm_mod') {
            if ($mod !== ($ctx['mod_slots'] ?? [])) {
                return $this->evaluateModify($conversation, $ctx, $mod, $lang);
            }
            if (Affirmation::isYes($text)) {
                $this->requests->submitModification($reservation, $mod);
                $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
                return Replies::modifyInReview($this->displayFromSlots($mod), $lang);
            }
            if (Affirmation::isNo($text)) {
                $this->persist($conversation, Conversation::STATE_IDLE, ['slots' => SlotFiller::empty()]);
                return Replies::cancelKept($lang);
            }
            return Replies::confirmSummary($mod, $lang);
        }

        // collect
        if ($mod === ($ctx['mod_slots'] ?? []) && ! array_filter($interp['slots'])) {
            return Replies::askWhatToChange($this->summaryById($cands, $ctx['target_id']) ?? [], $lang);
        }
        return $this->evaluateModify($conversation, $ctx, $mod, $lang);
    }

    private function evaluateModify(Conversation $conversation, array $ctx, array $mod, string $lang): string
    {
        if ($this->pastDate($mod['date'] ?? null)) {
            $mod['date'] = null;
            $this->persist($conversation, Conversation::STATE_MODIFYING, array_merge($ctx, ['stage' => 'collect', 'mod_slots' => $mod]));
            return Replies::pastDate($lang);
        }
        if ($miss = SlotFiller::missing($mod)) {
            $this->persist($conversation, Conversation::STATE_MODIFYING, array_merge($ctx, ['stage' => 'collect', 'mod_slots' => $mod]));
            return SlotFiller::promptFor($miss, $lang);
        }
        $this->persist($conversation, Conversation::STATE_MODIFYING, array_merge($ctx, ['stage' => 'confirm_mod', 'mod_slots' => $mod]));
        return Replies::confirmSummary($mod, $lang);
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
        $wasHuman = $conversation->state === Conversation::STATE_HUMAN;
        $this->persist($conversation, Conversation::STATE_HUMAN, $ctx);
        if (! $wasHuman) {
            event(new ConversationHandedOff($conversation->id, $contact->id, 'guest_request', $contact->wa_id, $contact->name));
        }
        return Replies::handoff($lang);
    }

    // --------------------------------------------------------------- helpers

    private function pastDate(?string $date): bool
    {
        return $date !== null && $date < Carbon::now('America/Mexico_City')->toDateString();
    }

    private function persist(Conversation $conversation, string $state, array $context): void
    {
        $was = $conversation->state;
        $conversation->state   = $state;
        $conversation->context = $context;
        if ($was !== $state) {
            $conversation->state_changed_at = now();
        }
        $conversation->save();
    }

    private function slotsFromReservation(int $id): array
    {
        $r = Reservation::find($id);
        if (! $r) {
            return SlotFiller::empty();
        }
        return [
            'name'              => $r->name,
            'party_size'        => (int) $r->party_size,
            'time'              => substr((string) $r->reserved_time, 0, 5),
            'date'              => $r->reserved_date instanceof Carbon ? $r->reserved_date->toDateString() : (string) $r->reserved_date,
            'reference_contact' => $r->reference_contact,
        ];
    }

    private function displayFromSlots(array $s): array
    {
        return ['date' => $s['date'], 'time' => $s['time'], 'party_size' => $s['party_size'], 'name' => $s['name']];
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
