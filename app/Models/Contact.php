<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    protected $fillable = [
        'wa_id',
        'name',
        'locale',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Upcoming confirmed reservations, soonest first.
     */
    public function upcomingReservations(): HasMany
    {
        return $this->reservations()
            ->where('status', 'confirmed')
            ->whereDate('reserved_date', '>=', now()->toDateString())
            ->orderBy('reserved_date')
            ->orderBy('reserved_time');
    }
}
