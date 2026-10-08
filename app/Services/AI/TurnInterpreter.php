<?php

namespace App\Services\AI;

use App\Models\Message;
use Illuminate\Support\Collection;

/**
 * Turns the latest guest message (+ recent history + in-progress slots) into a
 * normalized interpretation. Always returns something safe even if the model
 * fails. T11: normalizes the new reference_contact slot.
 */
class TurnInterpreter
{
    public function __construct(
        private OpenAIClient $client,
        private PromptBuilder $prompts,
    ) {}

    public function interpret(string $currentText, Collection $history, array $currentSlots = []): array
    {
        $messages = [['role' => 'system', 'content' => $this->prompts->system()]];

        foreach ($history as $m) {
            /** @var Message $m */
            if (! $m->body) {
                continue;
            }
            $messages[] = [
                'role'    => $m->direction === Message::DIR_IN ? 'user' : 'assistant',
                'content' => $m->body,
            ];
        }

        $messages[] = [
            'role'    => 'system',
            'content' => 'Solicitud en progreso (datos ya recabados, no los vuelvas a pedir): '
                . json_encode($currentSlots, JSON_UNESCAPED_UNICODE),
        ];
        $messages[] = ['role' => 'user', 'content' => $currentText];

        return $this->normalize($this->client->structured($messages, TurnSchema::schema()));
    }

    private function normalize(?array $raw): array
    {
        $intent = $raw['intent'] ?? 'other';
        if (! in_array($intent, TurnSchema::INTENTS, true)) {
            $intent = 'other';
        }
        $language = ($raw['language'] ?? 'es') === 'en' ? 'en' : 'es';

        $slots = $raw['slots'] ?? [];
        $party = $slots['party_size'] ?? null;
        $party = is_numeric($party) ? (int) $party : null;
        if ($party !== null && $party < 1) {
            $party = null;
        }

        $faq = $raw['faq_answer'] ?? null;
        $faq = is_string($faq) && trim($faq) !== '' ? trim($faq) : null;

        return [
            'intent'     => $intent,
            'language'   => $language,
            'slots'      => [
                'name'              => $this->str($slots['name'] ?? null),
                'party_size'        => $party,
                'time'              => $this->cleanTime($slots['time'] ?? null),
                'date'              => $this->cleanDate($slots['date'] ?? null),
                'reference_contact' => $this->str($slots['reference_contact'] ?? null),
            ],
            'faq_answer' => $faq,
            '_model_ok'  => $raw !== null,
        ];
    }

    private function str($v): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $v = trim($v);
        return $v === '' ? null : $v;
    }

    private function cleanDate(?string $d): ?string
    {
        return $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
    }

    private function cleanTime(?string $t): ?string
    {
        if ($t && preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
            $h = (int) $m[1]; $min = (int) $m[2];
            if ($h >= 0 && $h < 24 && $min >= 0 && $min < 60) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }
        return null;
    }
}
