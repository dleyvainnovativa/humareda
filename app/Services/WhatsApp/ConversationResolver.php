<?php

namespace App\Services\WhatsApp;

use App\Models\Contact;
use App\Models\Conversation;

/**
 * Resolves the Contact + Conversation for an inbound wa_id, creating them on
 * first contact and keeping last-seen / last-inbound timestamps fresh.
 *
 * last_inbound_at is important beyond bookkeeping: it drives the 24h
 * customer-service window used by reminders (T9) to choose free-form vs
 * paid template.
 */
class ConversationResolver
{
    public function resolve(string $waId, ?string $profileName = null): Contact
    {
        $contact = Contact::firstOrCreate(
            ['wa_id' => $waId],
            ['name' => $profileName],
        );

        // Fill the name if we learn it later and don't have one yet.
        if (! $contact->name && $profileName) {
            $contact->name = $profileName;
        }
        $contact->last_seen_at = now();
        $contact->save();

        $conversation = $contact->conversation()->firstOrCreate(
            ['contact_id' => $contact->id],
            ['state' => Conversation::STATE_IDLE],
        );
        $conversation->last_inbound_at = now();
        $conversation->save();

        // Ensure the relation is loaded for callers.
        $contact->setRelation('conversation', $conversation);

        return $contact;
    }
}
