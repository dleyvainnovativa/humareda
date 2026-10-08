<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\Reservation;
use App\Models\SlotOccupancy;
use App\Services\Availability\AvailabilityService;
use App\Services\Availability\BookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Booking accounting sanity check (NOT a true concurrency test — see GO_LIVE.md
 * for the two-terminal lock test). Fires N sequential bookings of a given party
 * at a date/time and asserts the start slot never exceeds max_covers_per_slot.
 *
 *   php artisan booking:simulate 2026-10-15 20:00 --party=4 --count=20 --cleanup
 */
class BookingSimulate extends Command
{
    protected $signature = 'booking:simulate {date} {time} {--party=2} {--count=20} {--cleanup}';
    protected $description = 'Fire sequential test bookings and verify covers never exceed the slot max.';

    public function handle(BookingService $booking, AvailabilityService $availability): int
    {
        $date  = $this->argument('date');
        $time  = $this->argument('time');
        $party = (int) $this->option('party');
        $count = (int) $this->option('count');

        $cfg = $availability->configFor($date);
        if (! $cfg) {
            $this->error('No service-hours config for that weekday.');
            return self::FAILURE;
        }
        $max = $cfg['max_covers_per_slot'];
        $this->info("Max covers/slot = {$max}; firing {$count} bookings of party {$party} at {$date} {$time}.");

        $contact = Contact::firstOrCreate(['wa_id' => 'SIM-'.uniqid()], ['name' => 'Simulación']);
        $confirmed = 0; $rejected = 0; $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $out = $booking->book($contact->id, $date, $time, $party, "Sim {$i}", null, 'staff');
            if ($out['reservation']) { $confirmed++; $ids[] = $out['reservation']->id; }
            else { $rejected++; }
        }

        $peak = (int) SlotOccupancy::where('reserved_date', $date)->max('covers');
        $this->table(['metric', 'value'], [
            ['confirmed', $confirmed], ['rejected', $rejected],
            ['peak covers in any slot', $peak], ['max allowed', $max],
            ['oversold?', $peak > $max ? 'YES — BUG' : 'no'],
        ]);

        if ($this->option('cleanup')) {
            DB::transaction(function () use ($ids, $date) {
                Reservation::whereIn('id', $ids)->delete();
                SlotOccupancy::where('reserved_date', $date)->delete();
            });
            $this->info('Cleaned up simulated rows.');
        }

        return $peak > $max ? self::FAILURE : self::SUCCESS;
    }
}
