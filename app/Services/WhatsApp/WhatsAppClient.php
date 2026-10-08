<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client over the WhatsApp Cloud API (Graph). Handles the outbound
 * half of the pipe: sending text, marking messages read, and (stubbed for
 * later tiers) templates and media download.
 *
 * All calls target the configured phone_number_id — in dev this is the
 * TEST number; in production it becomes the live 229 line (T10).
 */
class WhatsAppClient
{
    private string $base;
    private string $token;
    private ?string $phoneNumberId;

    public function __construct()
    {
        $version              = config('services.whatsapp.graph_version', 'v21.0');
        $this->base           = "https://graph.facebook.com/{$version}";
        $this->token          = (string) config('services.whatsapp.token');
        $this->phoneNumberId  = config('services.whatsapp.phone_number_id');
    }

    /**
     * Send a free-form text message (valid inside the 24h customer window).
     * Returns the WhatsApp message id (wamid) on success, or null on failure.
     */
    public function sendText(string $to, string $body): ?string
    {
        return $this->post([
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['preview_url' => false, 'body' => $body],
        ]);
    }

    /**
     * Send an approved template message (used for out-of-window reminders in T9).
     * $components follows the Graph API template component structure.
     */
    public function sendTemplate(string $to, string $template, string $lang = 'es_MX', array $components = []): ?string
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => [
                'name'     => $template,
                'language' => ['code' => $lang],
            ],
        ];
        if ($components) {
            $payload['template']['components'] = $components;
        }

        return $this->post($payload);
    }

    /**
     * Mark an inbound message as read (the blue ticks). Best-effort.
     */
    public function markRead(string $wamid): void
    {
        try {
            Http::withToken($this->token)
                ->post("{$this->base}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'status'            => 'read',
                    'message_id'        => $wamid,
                ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp markRead failed', ['wamid' => $wamid, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Download inbound media by media id (used for voicenotes in T7).
     * Returns raw bytes or null. Two-step: resolve URL, then fetch.
     */
    public function downloadMedia(string $mediaId): ?string
    {
        try {
            $meta = Http::withToken($this->token)->get("{$this->base}/{$mediaId}")->json();
            $url  = $meta['url'] ?? null;
            if (! $url) {
                return null;
            }
            // The media URL requires the same bearer token.
            $bytes = Http::withToken($this->token)->get($url);

            return $bytes->successful() ? $bytes->body() : null;
        } catch (\Throwable $e) {
            Log::warning('WhatsApp downloadMedia failed', ['media' => $mediaId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * POST a message payload and return the resulting wamid, or null.
     */
    private function post(array $payload): ?string
    {
        if (! $this->phoneNumberId || ! $this->token) {
            Log::error('WhatsApp not configured (phone_number_id/token missing).');
            return null;
        }

        try {
            $res = Http::withToken($this->token)
                ->post("{$this->base}/{$this->phoneNumberId}/messages", $payload);

            if ($res->failed()) {
                Log::error('WhatsApp send failed', ['status' => $res->status(), 'body' => $res->body()]);
                return null;
            }

            return data_get($res->json(), 'messages.0.id');
        } catch (\Throwable $e) {
            Log::error('WhatsApp send exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
