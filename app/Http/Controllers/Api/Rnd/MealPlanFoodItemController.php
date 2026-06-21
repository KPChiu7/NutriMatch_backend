<?php

namespace App\Http\Controllers\Api\Rnd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rnd\MealPlanFoodItemRequest;
use App\Models\MealPlanMeal;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Food item management within a meal slot.
 *
 * RA 10173 compliance: only food_name and external_food_id are ever
 * persisted for non-FEL sources (fnri_fct, usda, custom). Nutrient
 * data fetched from external APIs at selection time must be discarded
 * by the frontend after display — it is never sent to or stored by
 * this endpoint.
 *
 * Food items do not affect the parent MealPlan's total_* exchange
 * columns — those are driven by the meal slot's own exchange fields
 * (vegetable_exchanges, fruit_exchanges, etc.), not by counting
 * individual food items. See MealPlanMealController::recalculateTotals().
 */
class MealPlanFoodItemController extends Controller
{
    /**
     * List all food items for a meal slot owned by the authenticated RND.
     */
    public function index(int $mealPlanId, int $mealId, Request $request): JsonResponse
    {
        $meal = $this->findOwnedMeal($mealPlanId, $mealId, $request);

        $items = $meal->foodItems()->with('felItem:id,name,local_name,household_measure')->get();

        return response()->json(['food_items' => $items]);
    }

    /**
     * View a single food item.
     */
    public function show(int $mealPlanId, int $mealId, int $id, Request $request): JsonResponse
    {
        $meal = $this->findOwnedMeal($mealPlanId, $mealId, $request);

        $item = $meal->foodItems()->with('felItem')->findOrFail($id);

        return response()->json(['food_item' => $item]);
    }

    /**
     * Assign a new food item to a meal slot.
     */
    public function store(int $mealPlanId, int $mealId, MealPlanFoodItemRequest $request): JsonResponse
    {
        $meal = $this->findOwnedMeal($mealPlanId, $mealId, $request);

        $item = $meal->foodItems()->create([
            'food_item_id'      => $request->source_type === 'fel' ? $request->food_item_id : null,
            'food_name'         => $request->food_name,
            'source_type'       => $request->source_type,
            'external_food_id'  => $request->external_food_id,
            'exchanges'         => $request->exchanges,
            'household_measure' => $request->household_measure,
            'notes'             => $request->notes,
        ]);

        AuditService::log(
            'mealplan.fooditem.created',
            "RND added food item '{$item->food_name}' ({$item->source_type}) to meal slot #{$meal->id}."
        );

        return response()->json(['message' => 'Food item added.', 'food_item' => $item], 201);
    }

    /**
     * Update a food item's exchanges, measure, or notes.
     */
    public function update(int $mealPlanId, int $mealId, int $id, MealPlanFoodItemRequest $request): JsonResponse
    {
        $meal = $this->findOwnedMeal($mealPlanId, $mealId, $request);

        $item = $meal->foodItems()->findOrFail($id);

        $item->update([
            'food_item_id'      => $request->source_type === 'fel' ? $request->food_item_id : null,
            'food_name'         => $request->food_name,
            'source_type'       => $request->source_type,
            'external_food_id'  => $request->external_food_id,
            'exchanges'         => $request->exchanges,
            'household_measure' => $request->household_measure,
            'notes'             => $request->notes,
        ]);

        AuditService::log('mealplan.fooditem.updated', "RND updated food item #{$item->id} on meal slot #{$meal->id}.");

        return response()->json(['message' => 'Food item updated.', 'food_item' => $item->fresh()]);
    }

    /**
     * Remove a food item from a meal slot.
     */
    public function destroy(int $mealPlanId, int $mealId, int $id, Request $request): JsonResponse
    {
        $meal = $this->findOwnedMeal($mealPlanId, $mealId, $request);

        $item = $meal->foodItems()->findOrFail($id);
        $item->delete();

        AuditService::log('mealplan.fooditem.deleted', "RND removed food item #{$id} from meal slot #{$meal->id}.");

        return response()->json(['message' => 'Food item removed.']);
    }

    /**
     * Scope a meal slot lookup through its parent plan to the
     * authenticated RND's relationships.
     */
    protected function findOwnedMeal(int $mealPlanId, int $mealId, Request $request): MealPlanMeal
    {
        return MealPlanMeal::where('meal_plan_id', $mealPlanId)
            ->whereHas('mealPlan.relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->findOrFail($mealId);
    }
}
