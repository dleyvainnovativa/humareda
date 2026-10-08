<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_SHOW   = 'no_show';

    protected $fillable = [
        'contact_id',
        'reserved_date',
        'reserved_time',
        'party_size',
        'name',
        'status',
        'source',
        'notes',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'reserved_date' => 'date',
            'party_size'    => 'integer',
            'cancelled_at'  => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(ReservationReminder::class);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereDate('reserved_date', '>=', now()->toDateString());
    }
}
