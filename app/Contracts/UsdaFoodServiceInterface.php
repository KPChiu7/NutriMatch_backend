<?php

namespace App\Contracts;

/**
 * Contract for the USDA FoodData Central API service.
 * All external API logic must be behind this interface.
 */
interface UsdaFoodServiceInterface
{
    /**
     * Search for foods by name.
     *
     * @param  string $query     The search term
     * @param  int    $pageSize  Results per page (max 25 per USDA limits)
     * @return array             Normalized search results
     */
    public function search(string $query, int $pageSize = 10): array;

    /**
     * Get detailed nutrition information for a specific food.
     *
     * @param  string $fdcId  The USDA FoodData Central ID
     * @return array|null     Normalized food detail, or null if not found
     */
    public function getFood(string $fdcId): ?array;
}
