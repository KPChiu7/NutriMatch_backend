<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Seeds:
     *  1. Admin user (password: admin1234 — CHANGE IN PRODUCTION)
     *  2. Food Exchange Categories (7 FNRI FEL 4th Ed.)
     *  3. System Settings defaults
     *
     * Note: The 550 FEL food items are seeded via FoodExchangeItemSeeder
     * which reads from the SQL file to keep this file manageable.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            FoodExchangeCategorySeeder::class,
            FoodExchangeItemSeeder::class,  // 550 FNRI FEL items (4th Ed. 2020)
            SystemSettingSeeder::class,
        ]);
    }
}
