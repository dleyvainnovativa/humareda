<?php

namespace App\Services\Bot;

/**
 * Pure slot-filling for the reservation REQUEST (T11). Five fields, asked in
 * the order the client listed them:
 *   name -> party_size -> time -> date -> reference_contact
 */
class SlotFiller
{
    public const FIELDS = ['name', 'party_size', 'time', 'date', 'reference_contact'];

    public static function empty(): array
    {
        return ['name' => null, 'party_size' => null, 'time' => null, 'date' => null, 'reference_contact' => null];
    }

    public static function merge(array $existing, array $extracted): array
    {
        $out = array_merge(self::empty(), $existing);
        foreach (self::FIELDS as $f) {
            if (($extracted[$f] ?? null) !== null && $extracted[$f] !== '') {
                $out[$f] = $extracted[$f];
            }
        }
        return $out;
    }

    public static function missing(array $slots): ?string
    {
        foreach (self::FIELDS as $f) {
            if (($slots[$f] ?? null) === null || $slots[$f] === '') {
                return $f;
            }
        }
        return null;
    }

    public static function complete(array $slots): bool
    {
        return self::missing($slots) === null;
    }

    public static function promptFor(string $field, string $lang): string
    {
        return match ($field) {
            'name'              => Replies::askName($lang),
            'party_size'        => Replies::askParty($lang),
            'time'              => Replies::askTime($lang),
            'date'              => Replies::askDate($lang),
            'reference_contact' => Replies::askReference($lang),
            default             => Replies::fallback($lang),
        };
    }
}
