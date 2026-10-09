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
        $cacheKey = "setting:{$key}";
        $row = Cache::get($cacheKey);

        // Cache a plain array, NOT the Eloquent model. A legacy build cached the
        // model itself, which the database/file store returns as
        // __PHP_Incomplete_Class (or a model) on later requests. Anything that
        // isn't our [value, type] array is treated as a miss and rebuilt, so the
        // poisoned blob self-heals without needing a manual `cache:clear`.
        if (! is_array($row) || ! array_key_exists('type', $row)) {
            $model = static::query()->where('key', $key)->first();
            $row = ['value' => $model?->value, 'type' => $model?->type];
            Cache::forever($cacheKey, $row);
        }

        if ($row['type'] === null) {
            return $default;
        }

        return match ($row['type']) {
            'int'  => (int) $row['value'],
            'bool' => filter_var($row['value'], FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($row['value'], true),
            default => $row['value'],
        };
    }

    public static function put(string $key, mixed $value, string $type = 'string'): void
    {
        $stored = $type === 'json' ? json_encode($value) : (string) $value;

        static::updateOrCreate(['key' => $key], ['value' => $stored, 'type' => $type]);
        Cache::forget("setting:{$key}");
    }
}
