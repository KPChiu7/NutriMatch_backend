<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * RND meal plan prescriptions per clinical condition.
 * Total exchange columns are denormalized for fast reporting.
 */
class MealPlan extends Model
{
    protected $fillable = [
        'relationship_id',
        'name',
        'condition',
        'target_kcal',
        'total_vegetable',
        'total_fruit',
        'total_milk',
        'total_rice',
        'total_meat',
        'total_fat',
        'total_sugar',
        'notes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'target_kcal'    => 'decimal:2',
            'total_vegetable'=> 'decimal:1',
            'total_fruit'    => 'decimal:1',
            'total_milk'     => 'decimal:1',
            'total_rice'     => 'decimal:1',
            'total_meat'     => 'decimal:1',
            'total_fat'      => 'decimal:1',
            'total_sugar'    => 'decimal:1',
        ];
    }

    public function relationship(): BelongsTo
    {
        return $this->belongsTo(RndClientRelationship::class, 'relationship_id');
    }

    public function meals(): HasMany
    {
        return $this->hasMany(MealPlanMeal::class, 'meal_plan_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
