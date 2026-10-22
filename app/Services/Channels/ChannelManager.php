<?php

namespace App\Services\Channels;

use App\Enums\Channel;
use App\Models\Contact;
use App\Models\WhatsAppNumber;
use App\Services\Instagram\InstagramClient;
use App\Services\Messenger\MessengerClient;
use App\Services\WhatsApp\WhatsAppClient;

/**
 * Resolves the right ChannelClient for a channel or a contact. The one place
 * that maps channel -> concrete send client. Messenger and Instagram clients
 * are added here as their tiers land (T12 Phase B / C).
 */
class ChannelManager
{
    public function for(Channel $channel): ChannelClient
    {
        return match ($channel) {
            Channel::WhatsApp  => app(WhatsAppClient::class),
            Channel::Messenger => app(MessengerClient::class),
            Channel::Instagram => app(InstagramClient::class),
        };
    }

    public function forContact(Contact $contact): ChannelClient
    {
        // WhatsApp: send from the same business number the guest talks to
        // (Tech Provider / Coexistence). Falls back to the config number.
        if ($contact->channel === Channel::WhatsApp && $contact->wa_phone_number_id) {
            $client = app(WhatsAppClient::class);
            $number = WhatsAppNumber::findActive($contact->wa_phone_number_id);

            return $number ? $client->forNumber($number) : $client;
        }

        return $this->for($contact->channel);
    }

    /**
     * The WhatsApp client (typed) bound to a contact's business number — needed
     * where WhatsApp-only methods like sendTemplate() are used (reminders).
     */
    public function whatsapp(Contact $contact): WhatsAppClient
    {
        $client = app(WhatsAppClient::class);
        $number = WhatsAppNumber::findActive($contact->wa_phone_number_id);

        return $number ? $client->forNumber($number) : $client;
    }
}
