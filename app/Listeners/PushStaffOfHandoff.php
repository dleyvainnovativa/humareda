<?php

namespace App\Listeners;

use App\Events\ConversationHandedOff;
use App\Services\Push\PushSender;

/**
 * Pushes staff (Flutter app) when a conversation is handed to a human. Parallel
 * to NotifyStaffOfHandoff (email); same event, different channel. Fires only on
 * the automated handoff path (bot escalation), not manual take-over.
 */
class PushStaffOfHandoff
{
    public function __construct(private PushSender $push) {}

    public function handle(ConversationHandedOff $event): void
    {
        $who = $event->contactName ?: $event->waId ?: 'Un cliente';

        $reason = match ($event->reason) {
            'big_party' => 'grupo grande',
            'uncovered' => 'consulta fuera de cobertura',
            default     => 'pidió hablar con una persona',
        };

        $this->push->send(
            'Conversación requiere atención',
            "{$who} — {$reason}.",
            ['type' => 'handoff', 'contact_id' => $event->contactId],
        );
    }
}
