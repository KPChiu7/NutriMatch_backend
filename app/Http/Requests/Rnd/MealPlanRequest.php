<?php

namespace App\Http\Requests\Rnd;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates meal plan creation and update.
 *
 * total_* fields are normally auto-calculated server-side from the plan's
 * meal slots (see MealPlanMealController::recalculateTotals()). They are
 * accepted here only so an RND can manually override a total via PATCH;
 * on create, omit them and let the first meal slot populate them.
 */
class MealPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isRnd();
    }

    public function rules(): array
    {
        return [
            'relationship_id' => ['required_without:id', 'integer', 'exists:rnd_client_relationships,id'],
            'name'            => ['required', 'string', 'max:255'],
            'condition'       => ['required', 'in:diabetes,hypertension,renal,weight_loss,weight_gain,general'],
            'target_kcal'     => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'total_vegetable' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'total_fruit'     => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'total_milk'      => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'total_rice'      => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'total_meat'      => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'total_fat'       => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'total_sugar'     => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'notes'           => ['nullable', 'string', 'max:5000'],
            'status'          => ['nullable', 'in:active,archived'],
        ];
    }
}
