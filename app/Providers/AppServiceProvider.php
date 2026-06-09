<?php

namespace App\Providers;

use App\Contracts\PaymentServiceInterface;
use App\Contracts\UsdaFoodServiceInterface;
use App\Contracts\VideoSessionServiceInterface;
use App\Services\DailyCoVideoService;
use App\Services\PayMongoService;
use App\Services\UsdaFoodService;
use Illuminate\Support\ServiceProvider;

/**
 * Binds service interfaces to their concrete implementations.
 * To swap a provider (e.g. Stripe instead of PayMongo), only this file changes.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Payment gateway — currently PayMongo (Philippine market)
        $this->app->bind(PaymentServiceInterface::class, PayMongoService::class);

        // USDA FoodData Central food search
        $this->app->bind(UsdaFoodServiceInterface::class, UsdaFoodService::class);

        // Video consultation — currently Daily.co
        $this->app->bind(VideoSessionServiceInterface::class, DailyCoVideoService::class);
    }

    public function boot(): void
    {
        //
    }
}
