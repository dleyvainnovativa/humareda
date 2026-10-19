<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    public const TYPE_FIXED      = 'fixed';
    public const TYPE_PER_100G   = 'per_100_g';
    public const TYPE_NOT_LISTED = 'not_listed';

    protected $fillable = [
        'menu_category_id', 'ext_id', 'name', 'description',
        'portion_qty', 'portion_unit', 'price', 'price_type',
        'options', 'notes', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options'     => 'array',
            'notes'       => 'array',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Human price label, aware of per-weight ("S/T") and unlisted items. */
    public function priceLabel(): string
    {
        $mxn = fn ($n) => '$' . number_format((float) $n, 0);

        return match ($this->price_type) {
            self::TYPE_PER_100G => $this->price !== null
                ? "por peso, ~{$mxn($this->price)}/100 g (precio según tamaño)"
                : 'por peso (precio según tamaño)',
            self::TYPE_NOT_LISTED => 'precio no listado (pregunta al anfitrión)',
            default => $this->price !== null ? $mxn($this->price) : '—',
        };
    }

    /** "200 g", "3 pza", or '' when there is no portion. */
    public function portionLabel(): string
    {
        if ($this->portion_qty === null) {
            return '';
        }
        $qty = rtrim(rtrim(number_format((float) $this->portion_qty, 2), '0'), '.');

        return trim("{$qty} {$this->portion_unit}");
    }
}
