<?php

namespace App\Services\Channels;

use App\Enums\Channel;
use App\Models\Contact;
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
            Channel::WhatsApp => app(WhatsAppClient::class),
            // Channel::Messenger => app(MessengerClient::class),   // Phase B
            // Channel::Instagram => app(InstagramClient::class),   // Phase C
            default => throw new \RuntimeException("Channel '{$channel->value}' is not implemented yet."),
        };
    }

    public function forContact(Contact $contact): ChannelClient
    {
        return $this->for($contact->channel);
    }
}
