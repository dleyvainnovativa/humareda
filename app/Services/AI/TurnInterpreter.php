<?php

namespace App\Services\AI;

use App\Models\Message;
use Illuminate\Support\Collection;

/**
 * Turns the latest guest message (+ recent history + in-progress slots) into a
 * normalized interpretation the ConversationEngine can act on.
 *
 * Output shape (always returns something safe, even if the model fails):
 *   [
 *     'intent'     => string,
 *     'language'   => 'es'|'en',
 *     'slots'      => ['date'=>?string,'time'=>?string,'party_size'=>?int,'name'=>?string],
 *     'faq_answer' => ?string,
 *   ]
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

        // Recent turns for context (oldest first). Map in->user, out->assistant.
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

        // Tell the model what's already collected so it only fills the gaps.
        $messages[] = [
            'role'    => 'system',
            'content' => 'Reservación en progreso (datos ya recabados, no los vuelvas a pedir): '
                . json_encode($currentSlots, JSON_UNESCAPED_UNICODE),
        ];

        $messages[] = ['role' => 'user', 'content' => $currentText];

        $raw = $this->client->structured($messages, TurnSchema::schema());

        return $this->normalize($raw);
    }

    private function normalize(?array $raw): array
    {
        $intent = $raw['intent'] ?? 'other';
        if (! in_array($intent, TurnSchema::INTENTS, true)) {
            $intent = 'other';
        }

        $language = ($raw['language'] ?? 'es') === 'en' ? 'en' : 'es';

        $slots = $raw['slots'] ?? [];
        $date  = $this->cleanDate($slots['date'] ?? null);
        $time  = $this->cleanTime($slots['time'] ?? null);
        $party = $slots['party_size'] ?? null;
        $party = is_numeric($party) ? (int) $party : null;
        if ($party !== null && $party < 1) {
            $party = null;
        }
        $name = isset($slots['name']) && is_string($slots['name']) ? trim($slots['name']) : null;
        $name = $name === '' ? null : $name;

        $faq = $raw['faq_answer'] ?? null;
        $faq = is_string($faq) && trim($faq) !== '' ? trim($faq) : null;

        return [
            'intent'     => $intent,
            'language'   => $language,
            'slots'      => ['date' => $date, 'time' => $time, 'party_size' => $party, 'name' => $name],
            'faq_answer' => $faq,
            '_model_ok'  => $raw !== null,
        ];
    }

    private function cleanDate(?string $d): ?string
    {
        if (! $d) {
            return null;
        }
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
    }

    private function cleanTime(?string $t): ?string
    {
        if (! $t) {
            return null;
        }
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            if ($h >= 0 && $h < 24 && $min >= 0 && $min < 60) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }
        return null;
    }
}
