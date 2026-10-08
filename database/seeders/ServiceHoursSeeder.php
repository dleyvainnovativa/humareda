<?php

namespace Database\Seeders;

use App\Models\ServiceHour;
use Illuminate\Database\Seeder;

/**
 * Seeds per-weekday hours from the restaurant's published schedule:
 *   D–J (Sun–Thu): 13:00–22:00
 *   V–S (Fri–Sat): 13:00–24:00
 *
 * !! CAPACITY NUMBERS ARE PLACEHOLDERS !!
 * max_covers_per_slot, turn_minutes and auto_confirm_max must be confirmed
 * with the client before the T3 availability engine is trusted in production.
 * last_seating is derived as close_time minus one turn; adjust to taste.
 */
class ServiceHoursSeeder extends Seeder
{
    public function run(): void
    {
        // day_of_week: 0=Sun .. 6=Sat
        $weeknight = [
            'is_open'             => true,
            'open_time'           => '13:00',
            'close_time'          => '22:00',
            'last_seating'        => '20:00',   // 22:00 - 120min turn  (PLACEHOLDER)
            'slot_minutes'        => 30,
            'turn_minutes'        => 120,       // PLACEHOLDER
            'max_covers_per_slot' => 40,        // PLACEHOLDER
            'auto_confirm_max'    => 8,         // PLACEHOLDER
        ];

        $weekend = array_merge($weeknight, [
            'close_time'   => '00:00',          // midnight (next day)
            'last_seating' => '22:00',          // PLACEHOLDER
        ]);

        foreach ([0, 1, 2, 3, 4] as $dow) {
            ServiceHour::updateOrCreate(['day_of_week' => $dow], $weeknight);
        }
        foreach ([5, 6] as $dow) {
            ServiceHour::updateOrCreate(['day_of_week' => $dow], $weekend);
        }
    }
}
