<?php

namespace App\Services\AI;

/**
 * The strict JSON schema for one interpreted turn. The model NEVER acts — it
 * only understands: classifies intent, extracts booking slots, detects
 * language, and (for FAQ) returns a grounded answer or null.
 */
class TurnSchema
{
    public const INTENTS = [
        'make_reservation',
        'cancel_reservation',
        'modify_reservation',
        'check_reservation',
        'faq',
        'talk_to_human',
        'greeting',
        'other',
    ];

    public static function schema(): array
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['intent', 'language', 'slots', 'faq_answer'],
            'properties' => [
                'intent' => [
                    'type' => 'string',
                    'enum' => self::INTENTS,
                    'description' => 'The guest\'s primary intent for THIS message.',
                ],
                'language' => [
                    'type' => 'string',
                    'enum' => ['es', 'en'],
                    'description' => 'Language of the guest\'s message.',
                ],
                'slots' => [
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'required'             => ['date', 'time', 'party_size', 'name'],
                    'properties' => [
                        'date' => [
                            'type'        => ['string', 'null'],
                            'description' => 'Reservation date as YYYY-MM-DD, resolved from the CURRENT DATE given. Null if not stated.',
                        ],
                        'time' => [
                            'type'        => ['string', 'null'],
                            'description' => '24-hour HH:MM. Null if not stated.',
                        ],
                        'party_size' => [
                            'type'        => ['integer', 'null'],
                            'description' => 'Number of people. Null if not stated.',
                        ],
                        'name' => [
                            'type'        => ['string', 'null'],
                            'description' => 'Name the reservation is under, if the guest gave one. Null otherwise.',
                        ],
                    ],
                ],
                'faq_answer' => [
                    'type'        => ['string', 'null'],
                    'description' => 'ONLY for intent=faq: the answer, taken strictly from the provided knowledge base, in the guest\'s language. Null if the KB does not cover it or intent is not faq.',
                ],
            ],
        ];
    }
}
