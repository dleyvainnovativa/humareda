<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An FCM registration token for one installed instance of the Flutter staff app.
 */
class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
