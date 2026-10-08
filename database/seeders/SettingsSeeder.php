<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Global bot settings. Editable later in the Ajustes panel (T10).
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        Setting::put('restaurant_name', 'Humareda Prime', 'string');
        Setting::put('bot_enabled', true, 'bool');

        // Staff notification target for handoffs (T6).
        Setting::put('staff_notify_email', 'reservaciones@humaredaprime.com', 'string');

        // Same-day reminder timing (T9). Either a fixed local time...
        Setting::put('reminder_mode', 'fixed_time', 'string');   // fixed_time | hours_before
        Setting::put('reminder_fixed_time', '11:00', 'string');  // 11:00 morning-of
        Setting::put('reminder_hours_before', 3, 'int');         // used if mode = hours_before

        // Default bot language when detection is ambiguous.
        Setting::put('default_locale', 'es', 'string');
    }
}
