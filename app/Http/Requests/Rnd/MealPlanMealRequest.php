<?php

namespace App\Http\Requests\Rnd;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates meal slot creation and update within a meal plan.
 *
 * meal_plan_meals has a DB-level unique constraint on
 * (meal_plan_id, meal_time) — only one slot per meal time per plan.
 * The controller catches the resulting QueryException and returns a
 * friendly 422; this request only validates shape, not uniqueness,
 * since the scope (which meal_plan_id) is resolved from the route,
 * not the request body.
 */
class MealPlanMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRnd();
    }

    public function rules(): array
    {
        return [
            'meal_time'           => ['required_without:id', 'in:breakfast,am_snack,lunch,pm_snack,dinner,bedtime_snack'],
            'vegetable_exchanges' => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'fruit_exchanges'     => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'milk_exchanges'      => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'rice_exchanges'      => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'meat_exchanges'      => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'fat_exchanges'       => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'sugar_exchanges'     => ['nullable', 'numeric', 'min:0', 'max:99.9'],
            'meal_notes'          => ['nullable', 'string', 'max:2000'],
        ];
    }
}
