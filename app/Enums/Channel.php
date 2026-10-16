<?php

namespace App\Enums;

/**
 * A messaging channel the bot speaks on. All three are Meta Business Messaging
 * products sharing the same Graph API family, which is why one engine serves
 * them. The string value is what's stored on contacts.channel.
 */
enum Channel: string
{
    case WhatsApp  = 'whatsapp';
    case Messenger = 'messenger';
    case Instagram = 'instagram';

    /** Human label for the panel. */
    public function label(): string
    {
        return match ($this) {
            self::WhatsApp  => 'WhatsApp',
            self::Messenger => 'Messenger',
            self::Instagram => 'Instagram',
        };
    }

    /** Font Awesome brand icon class for the channel badge. */
    public function faIcon(): string
    {
        return match ($this) {
            self::WhatsApp  => 'fa-whatsapp',
            self::Messenger => 'fa-facebook-messenger',
            self::Instagram => 'fa-instagram',
        };
    }
}
