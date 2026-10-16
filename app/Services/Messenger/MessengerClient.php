<?php

namespace App\Services\Messenger;

use App\Services\Channels\ChannelClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Messenger (Page DM) send client — the outbound half of the Messenger
 * pipe (T12 Phase B). Same Graph API family as WhatsApp; recipients are PSIDs
 * (page-scoped user ids), auth is the Page access token, and the send endpoint
 * is POST /me/messages ("me" = the Page the token belongs to).
 */
class MessengerClient implements ChannelClient
{
    protected string $base;
    protected ?string $token;

    public function __construct()
    {
        $version     = config('services.meta.graph_version', 'v21.0');
        $this->base  = "https://graph.facebook.com/{$version}";
        $this->token = config('services.messenger.page_token');
    }

    public function sendText(string $to, string $body): ?string
    {
        return $this->post([
            'recipient'      => ['id' => $to],
            'messaging_type' => 'RESPONSE',
            'message'        => ['text' => $body],
        ]);
    }

    /** $ref is the sender PSID; marks the whole thread seen (best-effort). */
    public function markRead(string $ref): void
    {
        if (! $this->token) {
            return;
        }
        try {
            Http::withToken($this->token)->post("{$this->base}/me/messages", [
                'recipient'     => ['id' => $ref],
                'sender_action' => 'mark_seen',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Messenger markRead failed', ['psid' => $ref, 'error' => $e->getMessage()]);
        }
    }

    /** Messenger attachments arrive as direct URLs; $mediaId is that URL. */
    public function downloadMedia(string $mediaId): ?string
    {
        try {
            $res = Http::withToken($this->token)->get($mediaId);
            return $res->successful() ? $res->body() : null;
        } catch (\Throwable $e) {
            Log::warning('Messenger downloadMedia failed', ['url' => $mediaId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    public function profileName(string $externalId): ?string
    {
        if (! $this->token) {
            return null;
        }
        try {
            $res = Http::withToken($this->token)->get("{$this->base}/{$externalId}", ['fields' => 'name']);
            return $res->successful() ? ($res->json('name') ?: null) : null;
        } catch (\Throwable $e) {
            Log::warning('Messenger profileName failed', ['psid' => $externalId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function post(array $payload): ?string
    {
        if (! $this->token) {
            Log::error('Messenger not configured (page_token missing).');
            return null;
        }
        try {
            $res = Http::withToken($this->token)->post("{$this->base}/me/messages", $payload);
            if ($res->failed()) {
                Log::error('Messenger send failed', ['status' => $res->status(), 'body' => $res->body()]);
                return null;
            }
            return data_get($res->json(), 'message_id');
        } catch (\Throwable $e) {
            Log::error('Messenger send exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
