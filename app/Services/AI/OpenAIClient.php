<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal OpenAI Chat Completions client using Structured Outputs
 * (response_format = json_schema, strict). Returns the decoded JSON object the
 * model produced, or null on any failure — callers must handle null (we never
 * let a model error break the conversation).
 */
class OpenAIClient
{
    private string $base;
    private string $key;
    private string $model;

    public function __construct()
    {
        $this->base  = rtrim((string) config('services.openai.base_uri', 'https://api.openai.com/v1'), '/');
        $this->key   = (string) config('services.openai.key');
        $this->model = (string) config('services.openai.chat_model', 'gpt-4o-mini');
    }

    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @param array $schema  JSON schema for the "strict" structured output
     * @return array|null    decoded object, or null on failure
     */
    public function structured(array $messages, array $schema, string $schemaName = 'turn'): ?array
    {
        if ($this->key === '') {
            Log::error('OpenAI key not configured.');
            return null;
        }

        try {
            $res = Http::withToken($this->key)
                ->timeout(20)
                ->post("{$this->base}/chat/completions", [
                    'model'       => $this->model,
                    'temperature' => 0.2,
                    'messages'    => $messages,
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name'   => $schemaName,
                            'strict' => true,
                            'schema' => $schema,
                        ],
                    ],
                ]);

            if ($res->failed()) {
                Log::error('OpenAI request failed', ['status' => $res->status(), 'body' => $res->body()]);
                return null;
            }

            $content = data_get($res->json(), 'choices.0.message.content');
            if (! is_string($content)) {
                Log::error('OpenAI: no content in response');
                return null;
            }

            $decoded = json_decode($content, true);
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::error('OpenAI exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
