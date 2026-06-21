<?php

namespace App\Http\Requests\Rnd;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates food item assignment to a meal slot.
 *
 * RA 10173 compliance: nutrient data from external APIs (USDA, FNRI FCT)
 * is never persisted here — only food_name and external_food_id are
 * stored, enough to re-fetch from the source API later if needed.
 *
 * food_item_id is required only when source_type=fel (it FKs to the
 * seeded food_exchange_items table). For fnri_fct/usda/custom sources,
 * food_item_id must be left null and food_name is required instead.
 */
class MealPlanFoodItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRnd();
    }

    public function rules(): array
    {
        return [
            'source_type'       => ['required_without:id', 'in:fel,fnri_fct,usda,custom'],
            'food_item_id'      => ['nullable', 'integer', 'exists:food_exchange_items,id', 'required_if:source_type,fel'],
            'food_name'         => ['required_without:id', 'string', 'max:255'],
            'external_food_id'  => ['nullable', 'string', 'max:100'],
            'exchanges'         => ['required_without:id', 'numeric', 'min:0.1', 'max:99.9'],
            'household_measure' => ['nullable', 'string', 'max:100'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'food_item_id.required_if' => 'food_item_id is required when source_type is fel.',
        ];
    }
}
