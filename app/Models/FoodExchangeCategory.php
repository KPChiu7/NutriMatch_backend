<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * FNRI Food Exchange List categories (7 categories, seeded from 4th Ed. 2020).
 * This table is read-only in the application — seed data is authoritative.
 */
class FoodExchangeCategory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'name',
        'description',
        'kcal_per_exchange',
        'carbs_g',
        'protein_g',
        'fat_g',
        'color',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'kcal_per_exchange' => 'decimal:2',
            'carbs_g'           => 'decimal:2',
            'protein_g'         => 'decimal:2',
            'fat_g'             => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FoodExchangeItem::class, 'category_id');
    }
}
