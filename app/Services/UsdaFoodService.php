<?php

namespace App\Services;

use App\Contracts\UsdaFoodServiceInterface;
use App\Models\ApiCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * USDA FoodData Central API service.
 *
 * - All API keys are read from config('services.usda') — never hardcoded.
 * - Responses are cached in the api_cache table to prevent rate limit exhaustion.
 * - Nutrient data is NEVER persisted to meal_plan_food_items (RA 10173 compliance).
 */
class UsdaFoodService implements UsdaFoodServiceInterface
{
    private string $apiKey;
    private string $baseUrl;
    private int    $cacheTtlHours;

    public function __construct()
    {
        $this->apiKey       = config('services.usda.api_key');
        $this->baseUrl      = config('services.usda.base_url');
        $this->cacheTtlHours= config('services.usda.cache_ttl', 24);
    }

    /**
     * Search USDA FoodData Central for foods matching a query.
     * Results are cached in api_cache for cacheTtlHours.
     */
    public function search(string $query, int $pageSize = 10): array
    {
        $cacheKey = $this->buildCacheKey('search', ['q' => $query, 'ps' => $pageSize]);

        // Return cached result if available and not expired
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(config('services.usda.timeout', 15))
                ->get("{$this->baseUrl}/foods/search", [
                    'api_key'  => $this->apiKey,
                    'query'    => $query,
                    'pageSize' => $pageSize,
                ]);

            if ($response->failed()) {
                Log::error('USDA API search failed', [
                    'status' => $response->status(),
                    // NOTE: never log the api_key
                ]);
                return [];
            }

            $data = $this->normalizeSearchResults($response->json());
            $this->storeInCache($cacheKey, 'usda', $query, $data);

            return $data;

        } catch (\Exception $e) {
            Log::error('USDA API search exception', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get detailed food info by USDA fdcId.
     */
    public function getFood(string $fdcId): ?array
    {
        $cacheKey = $this->buildCacheKey('food', ['id' => $fdcId]);

        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = Http::timeout(config('services.usda.timeout', 15))
                ->get("{$this->baseUrl}/food/{$fdcId}", [
                    'api_key' => $this->apiKey,
                ]);

            if ($response->status() === 404) {
                return null;
            }

            if ($response->failed()) {
                Log::error('USDA API get food failed', ['fdcId' => $fdcId, 'status' => $response->status()]);
                return null;
            }

            $data = $this->normalizeFoodDetail($response->json());
            $this->storeInCache($cacheKey, 'usda', $fdcId, $data);

            return $data;

        } catch (\Exception $e) {
            Log::error('USDA API get food exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    // --------------------------------------------------------
    // Private helpers
    // --------------------------------------------------------

    private function buildCacheKey(string $type, array $params): string
    {
        return md5("usda:{$type}:" . json_encode($params));
    }

    private function getFromCache(string $key): ?array
    {
        $entry = ApiCache::where('cache_key', $key)
            ->where('expires_at', '>', now())
            ->first();

        if ($entry) {
            $entry->increment('hit_count');
            return $entry->response;
        }

        return null;
    }

    private function storeInCache(string $key, string $source, string $query, array $data): void
    {
        ApiCache::updateOrCreate(
            ['cache_key' => $key],
            [
                'source_api' => $source,
                'query_term' => $query,
                'response'   => $data,
                'expires_at' => now()->addHours($this->cacheTtlHours),
                'hit_count'  => 0,
            ]
        );
    }

    /**
     * Normalize USDA search results to a consistent internal format.
     * Only return fields the frontend needs; do not leak raw API payload.
     */
    private function normalizeSearchResults(array $raw): array
    {
        $foods = $raw['foods'] ?? [];

        return array_map(fn($food) => [
            'fdc_id'      => (string) ($food['fdcId'] ?? ''),
            'description' => $food['description'] ?? '',
            'data_type'   => $food['dataType'] ?? '',
            'brand_owner' => $food['brandOwner'] ?? null,
            'source'      => 'usda',
        ], $foods);
    }

    /**
     * Normalize food detail for display in the meal plan builder.
     * Nutrient data is included here for DISPLAY ONLY — callers must not
     * persist it to the database.
     */
    private function normalizeFoodDetail(array $raw): array
    {
        $nutrients = [];
        foreach ($raw['foodNutrients'] ?? [] as $n) {
            $name  = $n['nutrient']['name'] ?? '';
            $value = $n['amount'] ?? 0;
            $unit  = $n['nutrient']['unitName'] ?? '';
            $nutrients[$name] = "{$value} {$unit}";
        }

        return [
            'fdc_id'      => (string) ($raw['fdcId'] ?? ''),
            'description' => $raw['description'] ?? '',
            'data_type'   => $raw['dataType'] ?? '',
            'nutrients'   => $nutrients,
            'source'      => 'usda',
        ];
    }
}
