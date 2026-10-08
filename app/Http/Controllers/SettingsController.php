<?php

namespace App\Http\Controllers;

use App\Models\ServiceHour;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The settings screen — crucially, where the client's REAL capacity numbers
 * replace the placeholders seeded in T1 (covers/slot, turn, last seating,
 * auto_confirm_max per weekday), plus the global bot/reminder settings.
 */
class SettingsController extends Controller
{
    private const DOW = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'];

    public function edit(): View
    {
        $hours = ServiceHour::get()->keyBy('day_of_week');

        $general = [
            'restaurant_name'       => Setting::get('restaurant_name', 'Humareda Prime'),
            'staff_notify_email'    => Setting::get('staff_notify_email', ''),
            'bot_enabled'           => Setting::get('bot_enabled', true),
            'default_locale'        => Setting::get('default_locale', 'es'),
            'reminder_mode'         => Setting::get('reminder_mode', 'fixed_time'),
            'reminder_fixed_time'   => Setting::get('reminder_fixed_time', '11:00'),
            'reminder_hours_before' => Setting::get('reminder_hours_before', 3),
        ];

        return view('settings.edit', ['hours' => $hours, 'general' => $general, 'dow' => self::DOW]);
    }

    public function updateHours(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'days'                        => ['required', 'array'],
            'days.*.is_open'              => ['nullable', 'boolean'],
            'days.*.open_time'            => ['nullable', 'date_format:H:i'],
            'days.*.last_seating'         => ['nullable', 'date_format:H:i'],
            'days.*.close_time'           => ['nullable', 'date_format:H:i'],
            'days.*.slot_minutes'         => ['required', 'integer', 'min:5', 'max:240'],
            'days.*.turn_minutes'         => ['required', 'integer', 'min:15', 'max:480'],
            'days.*.max_covers_per_slot'  => ['required', 'integer', 'min:1', 'max:1000'],
            'days.*.auto_confirm_max'     => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        foreach ($validated['days'] as $dow => $row) {
            ServiceHour::updateOrCreate(['day_of_week' => (int) $dow], [
                'is_open'             => (bool) ($row['is_open'] ?? false),
                'open_time'           => $row['open_time'] ?? null,
                'last_seating'        => $row['last_seating'] ?? null,
                'close_time'          => $row['close_time'] ?? null,
                'slot_minutes'        => (int) $row['slot_minutes'],
                'turn_minutes'        => (int) $row['turn_minutes'],
                'max_covers_per_slot' => (int) $row['max_covers_per_slot'],
                'auto_confirm_max'    => (int) $row['auto_confirm_max'],
            ]);
        }

        return back()->with('status', 'Horario y cupos actualizados.');
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'restaurant_name'       => ['required', 'string', 'max:120'],
            'staff_notify_email'    => ['nullable', 'email', 'max:160'],
            'bot_enabled'           => ['nullable', 'boolean'],
            'default_locale'        => ['required', 'in:es,en'],
            'reminder_mode'         => ['required', 'in:fixed_time,hours_before'],
            'reminder_fixed_time'   => ['required', 'date_format:H:i'],
            'reminder_hours_before' => ['required', 'integer', 'min:1', 'max:24'],
        ]);

        Setting::put('restaurant_name', $data['restaurant_name']);
        Setting::put('staff_notify_email', $data['staff_notify_email'] ?? '');
        Setting::put('bot_enabled', $request->boolean('bot_enabled'), 'bool');
        Setting::put('default_locale', $data['default_locale']);
        Setting::put('reminder_mode', $data['reminder_mode']);
        Setting::put('reminder_fixed_time', $data['reminder_fixed_time']);
        Setting::put('reminder_hours_before', (int) $data['reminder_hours_before'], 'int');

        return back()->with('status', 'Ajustes guardados.');
    }
}
