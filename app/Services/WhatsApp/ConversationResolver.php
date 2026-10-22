<?php

namespace App\Services\WhatsApp;

use App\Enums\Channel;
use App\Models\Contact;
use App\Models\Conversation;

/**
 * Resolves the Contact + Conversation for an inbound message, creating them on
 * first contact and keeping last-seen / last-inbound timestamps fresh.
 *
 * Channel-neutral (T12): keyed on (channel, external_id). For WhatsApp the
 * external_id is the wa_id, and wa_id is kept populated so legacy WhatsApp code
 * paths keep working. Messenger / Instagram pass their own channel + PSID/IGSID.
 *
 * last_inbound_at drives the 24h customer-service window used by reminders (T9)
 * to choose free-form vs paid template.
 */
class ConversationResolver
{
    public function resolve(
        string $externalId,
        ?string $profileName = null,
        Channel $channel = Channel::WhatsApp,
        ?string $businessNumberId = null,
    ): Contact {
        $defaults = ['name' => $profileName];
        if ($channel === Channel::WhatsApp) {
            $defaults['wa_id'] = $externalId; // keep the legacy field populated for WhatsApp
            if ($businessNumberId) {
                $defaults['wa_phone_number_id'] = $businessNumberId;
            }
        }

        $contact = Contact::firstOrCreate(
            ['channel' => $channel->value, 'external_id' => $externalId],
            $defaults,
        );

        // Fill the name if we learn it later and don't have one yet.
        if (! $contact->name && $profileName) {
            $contact->name = $profileName;
        }
        // Keep the business number this guest talks to fresh (coexistence/multi-number).
        if ($channel === Channel::WhatsApp && $businessNumberId && $contact->wa_phone_number_id !== $businessNumberId) {
            $contact->wa_phone_number_id = $businessNumberId;
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
