<?php

namespace App\Http\Controllers\Api\Rnd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rnd\MealPlanMealRequest;
use App\Models\MealPlan;
use App\Models\MealPlanMeal;
use App\Services\AuditService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Meal slot management within a meal plan.
 *
 * meal_plan_meals has a DB-level unique constraint on
 * (meal_plan_id, meal_time) — a plan can have at most one breakfast,
 * one lunch, etc. store() catches the resulting QueryException (MySQL
 * error 1062) and returns a friendly 422 instead of a 500.
 *
 * Every create/update/delete here recalculates the parent MealPlan's
 * denormalized total_* columns by summing across all of its meal
 * slots. If the RND has manually overridden a total via
 * MealPlanController::update(), that override is replaced the next
 * time a meal slot changes — auto-calculation always wins on mutation,
 * per the project's stated behavior.
 */
class MealPlanMealController extends Controller
{
    /**
     * List all meal slots for a meal plan owned by the authenticated RND.
     */
    public function index(int $mealPlanId, Request $request): JsonResponse
    {
        $plan = $this->findOwnedPlan($mealPlanId, $request);

        $meals = $plan->meals()->with('foodItems')->orderByRaw(
            "FIELD(meal_time, 'breakfast','am_snack','lunch','pm_snack','dinner','bedtime_snack')"
        )->get();

        return response()->json(['meals' => $meals]);
    }

    /**
     * View a single meal slot (only if its parent plan belongs to this RND).
     */
    public function show(int $mealPlanId, int $id, Request $request): JsonResponse
    {
        $plan = $this->findOwnedPlan($mealPlanId, $request);

        $meal = $plan->meals()->with('foodItems')->findOrFail($id);

        return response()->json(['meal' => $meal]);
    }

    /**
     * Add a new meal slot to a meal plan.
     */
    public function store(int $mealPlanId, MealPlanMealRequest $request): JsonResponse
    {
        $plan = $this->findOwnedPlan($mealPlanId, $request);

        try {
            $meal = $plan->meals()->create([
                'meal_time'           => $request->meal_time,
                'vegetable_exchanges' => $request->vegetable_exchanges ?? 0,
                'fruit_exchanges'     => $request->fruit_exchanges ?? 0,
                'milk_exchanges'      => $request->milk_exchanges ?? 0,
                'rice_exchanges'      => $request->rice_exchanges ?? 0,
                'meat_exchanges'      => $request->meat_exchanges ?? 0,
                'fat_exchanges'       => $request->fat_exchanges ?? 0,
                'sugar_exchanges'     => $request->sugar_exchanges ?? 0,
                'meal_notes'          => $request->meal_notes,
            ]);
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return response()->json([
                    'message' => 'This meal plan already has a slot for '.$request->meal_time.'. Update the existing slot instead.',
                ], 422);
            }
            throw $e;
        }

        $this->recalculateTotals($plan);

        AuditService::log('mealplan.meal.created', "RND added {$meal->meal_time} slot to meal plan #{$plan->id}.");

        return response()->json(['message' => 'Meal slot added.', 'meal' => $meal], 201);
    }

    /**
     * Update an existing meal slot's exchange allocations.
     */
    public function update(int $mealPlanId, int $id, MealPlanMealRequest $request): JsonResponse
    {
        $plan = $this->findOwnedPlan($mealPlanId, $request);

        $meal = $plan->meals()->findOrFail($id);

        try {
            $meal->update($request->only([
                'meal_time', 'vegetable_exchanges', 'fruit_exchanges', 'milk_exchanges',
                'rice_exchanges', 'meat_exchanges', 'fat_exchanges', 'sugar_exchanges', 'meal_notes',
            ]));
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return response()->json([
                    'message' => 'This meal plan already has a slot for that meal time.',
                ], 422);
            }
            throw $e;
        }

        $this->recalculateTotals($plan);

        AuditService::log('mealplan.meal.updated', "RND updated meal slot #{$meal->id} on meal plan #{$plan->id}.");

        return response()->json(['message' => 'Meal slot updated.', 'meal' => $meal->fresh('foodItems')]);
    }

    /**
     * Remove a meal slot (cascades to its food items via FK).
     */
    public function destroy(int $mealPlanId, int $id, Request $request): JsonResponse
    {
        $plan = $this->findOwnedPlan($mealPlanId, $request);

        $meal = $plan->meals()->findOrFail($id);
        $meal->delete();

        $this->recalculateTotals($plan);

        AuditService::log('mealplan.meal.deleted', "RND removed meal slot #{$id} from meal plan #{$plan->id}.");

        return response()->json(['message' => 'Meal slot removed.']);
    }

    /**
     * Scope a meal plan lookup to the authenticated RND's relationships.
     */
    protected function findOwnedPlan(int $mealPlanId, Request $request): MealPlan
    {
        return MealPlan::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->findOrFail($mealPlanId);
    }

    /**
     * Recalculate the parent MealPlan's denormalized total_* columns by
     * summing the corresponding exchange column across all of its meal
     * slots. Called after every meal slot create/update/delete.
     */
    protected function recalculateTotals(MealPlan $plan): void
    {
        $sums = $plan->meals()->selectRaw('
                COALESCE(SUM(vegetable_exchanges), 0) as total_vegetable,
                COALESCE(SUM(fruit_exchanges), 0)     as total_fruit,
                COALESCE(SUM(milk_exchanges), 0)      as total_milk,
                COALESCE(SUM(rice_exchanges), 0)      as total_rice,
                COALESCE(SUM(meat_exchanges), 0)      as total_meat,
                COALESCE(SUM(fat_exchanges), 0)       as total_fat,
                COALESCE(SUM(sugar_exchanges), 0)     as total_sugar
            ')->first();

        $plan->update([
            'total_vegetable' => $sums->total_vegetable,
            'total_fruit'     => $sums->total_fruit,
            'total_milk'      => $sums->total_milk,
            'total_rice'      => $sums->total_rice,
            'total_meat'      => $sums->total_meat,
            'total_fat'       => $sums->total_fat,
            'total_sugar'     => $sums->total_sugar,
        ]);
    }
}
