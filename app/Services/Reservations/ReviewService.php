<?php

namespace App\Services\Reservations;

use App\Models\Message;
use App\Models\Reservation;
use App\Services\Bot\Replies;
use App\Services\WhatsApp\WhatsAppClient;

/**
 * Staff authorization of pending requests (T11). Approve -> confirmed + guest is
 * told it's confirmed (and the reminder observer schedules). Reject -> rejected
 * + guest is told, politely. Sends the guest message via WhatsApp (free-form;
 * the guest almost always just messaged, so the 24h window is open).
 */
class ReviewService
{
    public function __construct(private WhatsAppClient $wa) {}

    public function approve(Reservation $reservation, string $staffEmail): bool
    {
        if ($reservation->status !== Reservation::STATUS_PENDING) {
            return false;
        }

        $reservation->update([
            'status'      => Reservation::STATUS_CONFIRMED, // observer schedules the reminder
            'reviewed_at' => now(),
            'reviewed_by' => $staffEmail,
        ]);

        $this->notify($reservation, Replies::approved($this->display($reservation), $this->lang($reservation)));
        return true;
    }

    public function reject(Reservation $reservation, string $staffEmail): bool
    {
        if ($reservation->status !== Reservation::STATUS_PENDING) {
            return false;
        }

        $reservation->update([
            'status'      => Reservation::STATUS_REJECTED, // observer cancels any reminder
            'reviewed_at' => now(),
            'reviewed_by' => $staffEmail,
        ]);

        $this->notify($reservation, Replies::rejected($this->display($reservation), $this->lang($reservation)));
        return true;
    }

    private function notify(Reservation $reservation, string $body): void
    {
        $contact = $reservation->contact;
        if (! $contact) {
            return;
        }
        $wamid = $this->wa->sendText($contact->wa_id, $body);
        Message::create([
            'contact_id'    => $contact->id,
            'direction'     => Message::DIR_OUT,
            'type'          => 'text',
            'body'          => $body,
            'wa_message_id' => $wamid,
            'sender'        => 'bot',
            'created_at'    => now(),
        ]);
    }

    private function lang(Reservation $reservation): string
    {
        return $reservation->contact?->locale ?: 'es';
    }

    private function display(Reservation $r): array
    {
        return [
            'date'       => $r->reserved_date instanceof \Illuminate\Support\Carbon ? $r->reserved_date->toDateString() : (string) $r->reserved_date,
            'time'       => substr((string) $r->reserved_time, 0, 5),
            'party_size' => (int) $r->party_size,
            'name'       => $r->name,
        ];
    }
}
