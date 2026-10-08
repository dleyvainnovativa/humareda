<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    public const STATUS_PENDING   = 'pending';   // captured, awaiting human authorization (T11)
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED  = 'rejected';   // human declined the request (T11)
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_NO_SHOW   = 'no_show';

    protected $fillable = [
        'contact_id',
        'reserved_date',
        'reserved_time',
        'party_size',
        'name',
        'reference_contact',
        'status',
        'reviewed_at',
        'reviewed_by',
        'source',
        'notes',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'reserved_date' => 'date',
            'party_size'    => 'integer',
            'reviewed_at'   => 'datetime',
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

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeUpcoming($query)
    {
        return $query->whereDate('reserved_date', '>=', now()->toDateString());
    }
}
