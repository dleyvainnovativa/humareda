<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceHour extends Model
{
    protected $fillable = [
        'day_of_week',
        'is_open',
        'open_time',
        'last_seating',
        'close_time',
        'slot_minutes',
        'turn_minutes',
        'max_covers_per_slot',
        'auto_confirm_max',
    ];

    protected function casts(): array
    {
        return [
            'is_open'             => 'boolean',
            'slot_minutes'        => 'integer',
            'turn_minutes'        => 'integer',
            'max_covers_per_slot' => 'integer',
            'auto_confirm_max'    => 'integer',
        ];
    }
}
