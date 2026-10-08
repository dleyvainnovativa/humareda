<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type'];

    /**
     * Cached read with type casting. Falls back to $default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = Cache::rememberForever("setting:{$key}", function () use ($key) {
            return static::query()->where('key', $key)->first();
        });

        if (! $row) {
            return $default;
        }

        return match ($row->type) {
            'int'  => (int) $row->value,
            'bool' => filter_var($row->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($row->value, true),
            default => $row->value,
        };
    }

    public static function put(string $key, mixed $value, string $type = 'string'): void
    {
        $stored = $type === 'json' ? json_encode($value) : (string) $value;

        static::updateOrCreate(['key' => $key], ['value' => $stored, 'type' => $type]);
        Cache::forget("setting:{$key}");
    }
}
