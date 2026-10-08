<?php

namespace App\Services\Availability;

/**
 * Outcome of an availability check. Immutable value object the booking flow
 * (T4) reads to decide what to say and whether to write.
 *
 * status:
 *   available     -> confirmable now
 *   full          -> the requested slot(s) can't fit the party (see alternatives)
 *   outside_hours -> valid day but time is before open / after last seating
 *   closed        -> restaurant closed that weekday
 *   needs_human   -> party exceeds auto_confirm_max; route to staff
 *   invalid       -> nonsensical request (party < 1, or bigger than the room)
 */
class AvailabilityResult
{
    public function __construct(
        public readonly string $status,
        public readonly string $date,
        public readonly ?string $time,      // snapped start time, or null
        public readonly int $party,
        /** @var string[] same-day alternative start times */
        public readonly array $alternatives = [],
        public readonly ?int $autoConfirmMax = null,
    ) {}

    public function confirmable(): bool
    {
        return $this->status === 'available';
    }

    public function needsHuman(): bool
    {
        return $this->status === 'needs_human';
    }

    public function toArray(): array
    {
        return [
            'status'           => $this->status,
            'date'             => $this->date,
            'time'             => $this->time,
            'party'            => $this->party,
            'alternatives'     => $this->alternatives,
            'auto_confirm_max' => $this->autoConfirmMax,
        ];
    }
}
