<?php

namespace App\Services\Bot;

/**
 * Pure slot-filling helpers for the booking flow. No DB/framework so the
 * merge + missing-field logic can be unit-tested (see docs/verify_t4.php).
 *
 * Collection order (what we ask next): date -> party_size -> time -> name.
 */
class SlotFiller
{
    public const FIELDS = ['date', 'party_size', 'time', 'name'];

    public static function empty(): array
    {
        return ['date' => null, 'time' => null, 'party_size' => null, 'name' => null];
    }

    /**
     * Merge freshly extracted slots over existing ones. A non-null extracted
     * value wins (lets guests correct: "mejor 6 personas"); nulls don't erase.
     */
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

    /**
     * First missing field in ask-order, or null if all present.
     */
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
            'date'       => Replies::askDate($lang),
            'party_size' => Replies::askParty($lang),
            'time'       => Replies::askTime($lang),
            'name'       => Replies::askName($lang),
            default      => Replies::fallback($lang),
        };
    }
}
