<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FNRI FEL food items — 550 seed records from 4th Edition 2020.
 * Do NOT alter, remove, or reorder seed data.
 * Clinical flags (ok_for_diabetes, ok_for_renal, etc.) drive meal plan filtering.
 */
class FoodExchangeItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'category_id',
        'name',
        'local_name',
        'subcategory',
        'ep_grams',
        'household_measure',
        'is_high_sodium',
        'is_high_potassium',
        'is_high_phosphorus',
        'is_high_fiber',
        'is_low_gi',
        'ok_for_diabetes',
        'ok_for_hypertension',
        'ok_for_renal',
        'is_free_food',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'ep_grams'           => 'decimal:2',
            'is_high_sodium'     => 'boolean',
            'is_high_potassium'  => 'boolean',
            'is_high_phosphorus' => 'boolean',
            'is_high_fiber'      => 'boolean',
            'is_low_gi'          => 'boolean',
            'ok_for_diabetes'    => 'boolean',
            'ok_for_hypertension'=> 'boolean',
            'ok_for_renal'       => 'boolean',
            'is_free_food'       => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FoodExchangeCategory::class, 'category_id');
    }

    // --------------------------------------------------------
    // Scopes for clinical filtering
    // --------------------------------------------------------

    public function scopeForDiabetes($query)
    {
        return $query->where('ok_for_diabetes', true);
    }

    public function scopeForHypertension($query)
    {
        return $query->where('ok_for_hypertension', true);
    }

    public function scopeForRenal($query)
    {
        return $query->where('ok_for_renal', true);
    }

    public function scopeFreeFood($query)
    {
        return $query->where('is_free_food', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->whereFullText(['name', 'local_name'], $term);
    }
}
