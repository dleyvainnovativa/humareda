<?php

namespace App\Services\AI;

/**
 * Strict JSON schema for one interpreted turn. The model only understands:
 * intent, booking slots, language, and (for FAQ) a grounded answer.
 *
 * T11: adds `reference_contact` (email or phone the guest gives as a reference).
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
                'intent'   => ['type' => 'string', 'enum' => self::INTENTS],
                'language' => ['type' => 'string', 'enum' => ['es', 'en']],
                'slots' => [
                    'type'                 => 'object',
                    'additionalProperties' => false,
                    'required'             => ['name', 'party_size', 'time', 'date', 'reference_contact'],
                    'properties' => [
                        'name' => [
                            'type'        => ['string', 'null'],
                            'description' => 'Full name the reservation is under. Null if not given.',
                        ],
                        'party_size' => [
                            'type'        => ['integer', 'null'],
                            'description' => 'Number of people. Null if not stated.',
                        ],
                        'time' => [
                            'type'        => ['string', 'null'],
                            'description' => '24-hour HH:MM. Null if not stated.',
                        ],
                        'date' => [
                            'type'        => ['string', 'null'],
                            'description' => 'YYYY-MM-DD, resolved from the CURRENT DATE given. Null if not stated.',
                        ],
                        'reference_contact' => [
                            'type'        => ['string', 'null'],
                            'description' => 'A reference email OR phone number the guest provides. Null if not given.',
                        ],
                    ],
                ],
                'faq_answer' => [
                    'type'        => ['string', 'null'],
                    'description' => 'ONLY for intent=faq: answer strictly from the knowledge base, in the guest\'s language. Null if the KB does not cover it or intent is not faq.',
                ],
            ],
        ];
    }
}
