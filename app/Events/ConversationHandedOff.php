<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a conversation is handed to a human (guest asked, or a big party
 * / uncovered case routed to staff). Listeners notify staff; the panel shows
 * the conversation under "needs attention" (state = human).
 */
class ConversationHandedOff
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $contactId,
        public string $reason,        // 'guest_request' | 'big_party' | 'uncovered'
        public ?string $waId = null,
        public ?string $contactName = null,
    ) {}
}
