<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A connected WhatsApp business number (its phone_number_id + access token).
 * Created per onboarded number — directly, or via Embedded Signup for
 * Tech Provider / Coexistence onboarding.
 */
class WhatsAppNumber extends Model
{
    protected $table = 'whatsapp_numbers';

    protected $fillable = [
        'phone_number_id', 'waba_id', 'token', 'label', 'is_coexistence', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_coexistence' => 'boolean',
            'is_active'      => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function findActive(?string $phoneNumberId): ?self
    {
        if (! $phoneNumberId) {
            return null;
        }
        return static::active()->where('phone_number_id', $phoneNumberId)->first();
    }
}
