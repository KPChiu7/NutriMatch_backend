<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Food items assigned to a meal slot.
 *
 * source_type distinguishes FEL internal items from external API-sourced foods.
 * Nutrient data from external APIs is NEVER persisted here (RA 10173 compliance).
 * Only food_name and external_food_id are stored for external items.
 *
 * @property string $source_type fel|fnri_fct|usda|custom
 */
class MealPlanFoodItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'meal_plan_meal_id',
        'food_item_id',
        'food_name',
        'source_type',
        'external_food_id',
        'exchanges',
        'household_measure',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'exchanges' => 'decimal:1',
        ];
    }

    public function meal(): BelongsTo
    {
        return $this->belongsTo(MealPlanMeal::class, 'meal_plan_meal_id');
    }

    /** Only populated when source_type === 'fel'. */
    public function felItem(): BelongsTo
    {
        return $this->belongsTo(FoodExchangeItem::class, 'food_item_id');
    }
}
