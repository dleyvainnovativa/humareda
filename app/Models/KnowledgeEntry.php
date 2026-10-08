<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeEntry extends Model
{
    protected $fillable = [
        'category',
        'question_es',
        'answer_es',
        'question_en',
        'answer_en',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
