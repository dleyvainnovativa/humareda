<?php

namespace App\Services\AI;

use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Transcribes inbound WhatsApp voice notes via OpenAI's audio transcription
 * endpoint. WhatsApp delivers voice as audio/ogg (opus), which OpenAI accepts
 * directly — no ffmpeg, so this works on shared hosting.
 *
 * Returns the transcript text, or null if download/transcription failed or the
 * result was empty (caller then asks the guest to type).
 */
class TranscriptionService
{
    public function __construct(private WhatsAppClient $wa) {}

    public function transcribe(string $mediaId): ?string
    {
        $bytes = $this->wa->downloadMedia($mediaId);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $key   = (string) config('services.openai.key');
        $base  = rtrim((string) config('services.openai.base_uri', 'https://api.openai.com/v1'), '/');
        $model = (string) config('services.openai.audio_model', 'gpt-4o-transcribe');

        if ($key === '') {
            Log::error('OpenAI key not configured (transcription).');
            return null;
        }

        try {
            $res = Http::withToken($key)
                ->timeout(30)
                ->attach('file', $bytes, 'voice.ogg')
                ->post("{$base}/audio/transcriptions", [
                    'model'           => $model,
                    'response_format' => 'text',
                ]);

            if ($res->failed()) {
                Log::error('Transcription failed', ['status' => $res->status(), 'body' => $res->body()]);
                return null;
            }

            $text = trim($res->body());
            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            Log::error('Transcription exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
