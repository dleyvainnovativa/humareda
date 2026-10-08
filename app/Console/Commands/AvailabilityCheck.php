<?php

namespace App\Console\Commands;

use App\Services\Availability\BookingService;
use Illuminate\Console\Command;

/**
 * Quick manual availability probe against the live DB config + occupancy:
 *   php artisan availability:check 2026-09-25 20:00 4
 */
class AvailabilityCheck extends Command
{
    protected $signature = 'availability:check {date} {time} {party}';
    protected $description = 'Check reservation availability for a date/time/party (advisory).';

    public function handle(BookingService $booking): int
    {
        $result = $booking->check(
            $this->argument('date'),
            $this->argument('time'),
            (int) $this->argument('party'),
        );

        $this->table(['field', 'value'], [
            ['status', $result->status],
            ['confirmable', $result->confirmable() ? 'yes' : 'no'],
            ['date', $result->date],
            ['time (snapped)', $result->time ?? '—'],
            ['party', $result->party],
            ['alternatives', $result->alternatives ? implode(', ', $result->alternatives) : '—'],
            ['auto_confirm_max', $result->autoConfirmMax ?? '—'],
        ]);

        return self::SUCCESS;
    }
}
