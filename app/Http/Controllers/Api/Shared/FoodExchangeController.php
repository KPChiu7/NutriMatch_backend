<?php

namespace App\Http\Controllers\Api\Shared;

use App\Contracts\UsdaFoodServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\FoodExchangeCategory;
use App\Models\FoodExchangeItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Food exchange endpoints available to both RNDs and clients.
 * FNRI Food Exchange List 4th Edition (2020) — 550 items.
 * Also provides USDA FoodData Central search integration.
 */
class FoodExchangeController extends Controller
{
    public function __construct(
        private UsdaFoodServiceInterface $usdaService
    ) {}

    /**
     * List all FEL categories with their macro values.
     */
    public function categories(): JsonResponse
    {
        $categories = FoodExchangeCategory::orderBy('sort_order')->get();
        return response()->json(['categories' => $categories]);
    }

    /**
     * List FEL food items with optional filters.
     */
    public function items(Request $request): JsonResponse
    {
        $request->validate([
            'category_id'        => 'nullable|integer|exists:food_exchange_categories,id',
            'search'             => 'nullable|string|max:100',
            'ok_for_diabetes'    => 'nullable|boolean',
            'ok_for_hypertension'=> 'nullable|boolean',
            'ok_for_renal'       => 'nullable|boolean',
            'is_free_food'       => 'nullable|boolean',
            'per_page'           => 'nullable|integer|min:1|max:100',
        ]);

        $items = FoodExchangeItem::query()
            ->with('category:id,code,name,color')
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->when($request->search, fn($q) => $q->search($request->search))
            ->when($request->ok_for_diabetes, fn($q) => $q->forDiabetes())
            ->when($request->ok_for_hypertension, fn($q) => $q->forHypertension())
            ->when($request->ok_for_renal, fn($q) => $q->forRenal())
            ->when($request->is_free_food, fn($q) => $q->freeFood())
            ->paginate($request->per_page ?? 30);

        return response()->json($items);
    }

    /**
     * Search USDA FoodData Central API.
     * Results are not persisted — for display and meal plan reference only.
     * Responses are cached internally to prevent rate limit exhaustion.
     */
    public function searchUsda(Request $request): JsonResponse
    {
        $request->validate([
            'query'     => 'required|string|min:2|max:100',
            'page_size' => 'nullable|integer|min:1|max:25',
        ]);

        $results = $this->usdaService->search(
            query:    $request->query,
            pageSize: $request->page_size ?? 10,
        );

        return response()->json(['results' => $results]);
    }

    /**
     * Get detailed USDA food info by fdcId.
     * For display only — callers must NOT persist nutrient data.
     */
    public function usdaFoodDetail(string $fdcId): JsonResponse
    {
        $food = $this->usdaService->getFood($fdcId);

        if (! $food) {
            return response()->json(['message' => 'Food not found in USDA database.'], 404);
        }

        return response()->json(['food' => $food]);
    }
}
