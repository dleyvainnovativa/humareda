<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversation extends Model
{
    // State-machine states
    public const STATE_IDLE       = 'idle';
    public const STATE_BOOKING    = 'booking';
    public const STATE_CONFIRMING = 'confirming';
    public const STATE_CANCELLING = 'cancelling';
    public const STATE_MODIFYING  = 'modifying';
    public const STATE_HUMAN      = 'human';

    protected $fillable = [
        'contact_id',
        'state',
        'context',
        'last_inbound_at',
        'state_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'context'          => 'array',
            'last_inbound_at'  => 'datetime',
            'state_changed_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function isWithReservationBot(): bool
    {
        return $this->state !== self::STATE_HUMAN;
    }

    /**
     * True if the guest messaged within WhatsApp's 24h customer-service window,
     * meaning we may still reply free-form (free) instead of a paid template.
     */
    public function isWindowOpen(): bool
    {
        return $this->last_inbound_at
            && $this->last_inbound_at->gt(now()->subHours(24));
    }

    public function transitionTo(string $state): void
    {
        $this->update([
            'state'            => $state,
            'state_changed_at' => now(),
        ]);
    }
}
