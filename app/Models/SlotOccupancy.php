<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotOccupancy extends Model
{
    protected $table = 'slot_occupancy';

    protected $fillable = [
        'reserved_date',
        'slot_start',
        'covers',
    ];

    protected function casts(): array
    {
        return [
            'reserved_date' => 'date',
            'covers'        => 'integer',
        ];
    }
}
