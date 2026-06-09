<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Exchange allocations per meal slot within a meal plan. */
class MealPlanMeal extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'meal_plan_id',
        'meal_time',
        'vegetable_exchanges',
        'fruit_exchanges',
        'milk_exchanges',
        'rice_exchanges',
        'meat_exchanges',
        'fat_exchanges',
        'sugar_exchanges',
        'meal_notes',
    ];

    protected function casts(): array
    {
        return [
            'vegetable_exchanges'=> 'decimal:1',
            'fruit_exchanges'    => 'decimal:1',
            'milk_exchanges'     => 'decimal:1',
            'rice_exchanges'     => 'decimal:1',
            'meat_exchanges'     => 'decimal:1',
            'fat_exchanges'      => 'decimal:1',
            'sugar_exchanges'    => 'decimal:1',
        ];
    }

    public function mealPlan(): BelongsTo
    {
        return $this->belongsTo(MealPlan::class);
    }

    public function foodItems(): HasMany
    {
        return $this->hasMany(MealPlanFoodItem::class, 'meal_plan_meal_id');
    }
}
