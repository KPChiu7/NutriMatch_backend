<?php

namespace App\Http\Controllers\Api\Rnd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rnd\MealPlanRequest;
use App\Models\MealPlan;
use App\Models\RndClientRelationship;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * RND meal plan management — scoped strictly to the authenticated RND's
 * relationships. No active-relationship gate: a plan can be drafted
 * before the relationship request is formally accepted.
 *
 * total_* exchange columns are denormalized sums maintained by
 * MealPlanMealController::recalculateTotals() whenever a meal slot
 * changes. update() here lets the RND manually override any total —
 * that override persists until the next meal-slot mutation triggers
 * another recalculation.
 */
class MealPlanController extends Controller
{
    /**
     * List all meal plans for the authenticated RND, optionally scoped
     * to one relationship via ?relationship_id=.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'relationship_id' => ['nullable', 'integer'],
            'status'          => ['nullable', 'in:active,archived'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $plans = MealPlan::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->with(['relationship.client:id,first_name,last_name'])
            ->when($request->relationship_id, fn($q) => $q->where('relationship_id', $request->relationship_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json($plans);
    }

    /**
     * View a single meal plan with its full meal/food-item tree.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $plan = MealPlan::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->with(['relationship.client:id,first_name,last_name', 'meals.foodItems'])
            ->findOrFail($id);

        return response()->json(['meal_plan' => $plan]);
    }

    /**
     * Create a new meal plan for a relationship owned by the authenticated RND.
     * No active-status gate — relationship can be pending or active.
     */
    public function store(MealPlanRequest $request): JsonResponse
    {
        $relationship = RndClientRelationship::where('rnd_id', $request->user()->id)
            ->findOrFail($request->relationship_id);

        $plan = $relationship->mealPlans()->create([
            'name'            => $request->name,
            'condition'       => $request->condition,
            'target_kcal'     => $request->target_kcal,
            'total_vegetable' => $request->total_vegetable ?? 0,
            'total_fruit'     => $request->total_fruit ?? 0,
            'total_milk'      => $request->total_milk ?? 0,
            'total_rice'      => $request->total_rice ?? 0,
            'total_meat'      => $request->total_meat ?? 0,
            'total_fat'       => $request->total_fat ?? 0,
            'total_sugar'     => $request->total_sugar ?? 0,
            'notes'           => $request->notes,
            'status'          => $request->status ?? 'active',
        ]);

        AuditService::log('mealplan.created', "RND created meal plan #{$plan->id} for relationship #{$relationship->id}.");

        return response()->json(['message' => 'Meal plan created.', 'meal_plan' => $plan], 201);
    }

    /**
     * Update a meal plan's metadata. Can also be used to manually override
     * any auto-calculated total_* column.
     */
    public function update(int $id, MealPlanRequest $request): JsonResponse
    {
        $plan = MealPlan::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->findOrFail($id);

        $plan->update($request->only([
            'name', 'condition', 'target_kcal',
            'total_vegetable', 'total_fruit', 'total_milk',
            'total_rice', 'total_meat', 'total_fat', 'total_sugar',
            'notes', 'status',
        ]));

        AuditService::log('mealplan.updated', "RND updated meal plan #{$plan->id}.");

        return response()->json(['message' => 'Meal plan updated.', 'meal_plan' => $plan->fresh()]);
    }

    /**
     * Archive a meal plan (soft state change, not a hard delete —
     * ncp_records and other clinical references may still point to it).
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $plan = MealPlan::whereHas('relationship', fn($q) =>
                $q->where('rnd_id', $request->user()->id)
            )
            ->findOrFail($id);

        $plan->update(['status' => 'archived']);

        AuditService::log('mealplan.archived', "RND archived meal plan #{$plan->id}.");

        return response()->json(['message' => 'Meal plan archived.']);
    }
}
