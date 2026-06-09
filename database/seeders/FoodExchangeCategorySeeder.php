<?php

namespace Database\Seeders;

use App\Models\FoodExchangeCategory;
use Illuminate\Database\Seeder;

/**
 * Seeds the 7 FNRI Food Exchange List categories (4th Edition, 2020).
 * Data is authoritative and must not be altered.
 */
class FoodExchangeCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['id' => 1, 'code' => 'VEGE',  'name' => 'Vegetable', 'description' => 'Fresh: 1/2 cup raw (40g) or 1/2 cup cooked (45g). Approx 25 kcal per exchange.',         'kcal_per_exchange' => 25.00,  'carbs_g' => 5.00,  'protein_g' => 2.00, 'fat_g' => 0.00, 'color' => '#10B981', 'sort_order' => 1],
            ['id' => 2, 'code' => 'FRUIT', 'name' => 'Fruit',     'description' => 'Fresh, juice, and processed fruits. Approx 60 kcal per exchange.',                        'kcal_per_exchange' => 60.00,  'carbs_g' => 15.00, 'protein_g' => 0.00, 'fat_g' => 0.00, 'color' => '#F97316', 'sort_order' => 2],
            ['id' => 3, 'code' => 'MILK',  'name' => 'Milk',      'description' => 'Whole, low-fat, and non-fat milk. Approx 90 kcal (non-fat) to 150 kcal (whole).',        'kcal_per_exchange' => 90.00,  'carbs_g' => 12.00, 'protein_g' => 8.00, 'fat_g' => 0.00, 'color' => '#6366F1', 'sort_order' => 3],
            ['id' => 4, 'code' => 'RICE',  'name' => 'Rice',      'description' => 'Rice, corn, noodles, rootcrops, and bakery products. Approx 80 kcal per exchange.',      'kcal_per_exchange' => 80.00,  'carbs_g' => 15.00, 'protein_g' => 3.00, 'fat_g' => 0.00, 'color' => '#F59E0B', 'sort_order' => 4],
            ['id' => 5, 'code' => 'MEAT',  'name' => 'Meat',      'description' => 'Low-fat (55 kcal), medium-fat (100 kcal), and high-fat (130 kcal) meat per exchange.',   'kcal_per_exchange' => 55.00,  'carbs_g' => 0.00,  'protein_g' => 7.00, 'fat_g' => 1.00, 'color' => '#EF4444', 'sort_order' => 5],
            ['id' => 6, 'code' => 'FAT',   'name' => 'Fat',       'description' => 'Monounsaturated, polyunsaturated, and saturated fats. Approx 45 kcal per exchange.',     'kcal_per_exchange' => 45.00,  'carbs_g' => 0.00,  'protein_g' => 0.00, 'fat_g' => 5.00, 'color' => '#8B5CF6', 'sort_order' => 6],
            ['id' => 7, 'code' => 'SUGAR', 'name' => 'Sugar',     'description' => 'Simple sugars and sweets. Approx 20 kcal per exchange.',                                 'kcal_per_exchange' => 20.00,  'carbs_g' => 5.00,  'protein_g' => 0.00, 'fat_g' => 0.00, 'color' => '#EC4899', 'sort_order' => 7],
        ];

        foreach ($categories as $category) {
            FoodExchangeCategory::updateOrCreate(['id' => $category['id']], $category);
        }

        $this->command->info('7 FNRI FEL categories seeded.');
    }
}
