<?php

namespace App\Services\Channels;

/**
 * The outbound contract every channel's send client implements. The engine and
 * the panel talk to this, never to a concrete client, so adding Messenger /
 * Instagram is "implement this interface + register it in ChannelManager".
 *
 * $to is the channel-scoped recipient id (wa_id / PSID / IGSID).
 * Returns the provider message id on success, or null on failure.
 */
interface ChannelClient
{
    public function sendText(string $to, string $body): ?string;

    public function markRead(string $externalMessageId): void;

    public function downloadMedia(string $mediaId): ?string;
}
