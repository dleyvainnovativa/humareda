<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    protected $fillable = [
        'ext_id', 'name', 'description', 'source_page', 'extras', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'extras'      => 'array',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
            'source_page' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
