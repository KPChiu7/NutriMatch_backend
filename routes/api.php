<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ResourceController as AdminResourceController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Client\AppointmentController as ClientAppointmentController;
use App\Http\Controllers\Api\Client\PaymentController;
use App\Http\Controllers\Api\Client\PreConsultationScreeningController;
use App\Http\Controllers\Api\Client\ProgressRecordController as ClientProgressController;
use App\Http\Controllers\Api\Client\RelationshipController as ClientRelationshipController;
use App\Http\Controllers\Api\Client\ReminderController as ClientReminderController;
use App\Http\Controllers\Api\Client\ResourceController as ClientResourceController;
use App\Http\Controllers\Api\Client\ReviewController;
use App\Http\Controllers\Api\Client\RndMatchController;
use App\Http\Controllers\Api\Rnd\AppointmentController as RndAppointmentController;
use App\Http\Controllers\Api\Rnd\AvailabilityController;
use App\Http\Controllers\Api\Rnd\MealPlanController;
use App\Http\Controllers\Api\Rnd\MealPlanFoodItemController;
use App\Http\Controllers\Api\Rnd\MealPlanMealController;
use App\Http\Controllers\Api\Rnd\NcpController;
use App\Http\Controllers\Api\Rnd\ProgressRecordController as RndProgressController;
use App\Http\Controllers\Api\Rnd\RelationshipController as RndRelationshipController;
use App\Http\Controllers\Api\Rnd\ReminderController as RndReminderController;
use App\Http\Controllers\Api\Rnd\ResourceController as RndResourceController;
use App\Http\Controllers\Api\Shared\FoodExchangeController;
use App\Http\Controllers\Api\Shared\MessageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| NutriMatch API Routes
|--------------------------------------------------------------------------
|
| Route groups:
|  1. Public         — No authentication required
|  2. Authenticated  — Any verified user (admin | rnd | client)
|  3. Admin only     — role:admin
|  4. RND only       — role:rnd
|  5. Client only    — role:client
|  6. Webhooks       — External provider callbacks (no Sanctum, own sig validation)
|
*/

// ============================================================
// 1. PUBLIC — No authentication
// ============================================================
Route::prefix('auth')->group(function () {
    Route::post('/login',            [AuthController::class, 'login']);
    Route::post('/register/client',  [AuthController::class, 'registerClient']);
    Route::post('/register/rnd',     [AuthController::class, 'registerRnd']);
});

// ============================================================
// 6. WEBHOOKS — External providers (no Sanctum auth)
// Signature validation is handled inside the controller/service.
// ============================================================
Route::prefix('webhooks')->group(function () {
    Route::post('/paymongo', [PaymentController::class, 'webhook'])
         ->name('webhooks.paymongo');
});

// ============================================================
// 2. AUTHENTICATED — Any logged-in user
// ============================================================
Route::middleware('auth:sanctum')->group(function () {

    // --- Auth ---
    Route::get('/auth/me',     [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // --- Food Exchange (all roles) ---
    Route::prefix('food-exchange')->group(function () {
        Route::get('/categories',       [FoodExchangeController::class, 'categories']);
        Route::get('/items',            [FoodExchangeController::class, 'items']);
        Route::get('/usda/search',      [FoodExchangeController::class, 'searchUsda']);
        Route::get('/usda/{fdcId}',     [FoodExchangeController::class, 'usdaFoodDetail']);
    });

    // --- Messages (RND and client both participate) ---
    Route::prefix('relationships/{relationshipId}/messages')->group(function () {
        Route::get('/',   [MessageController::class, 'index']);
        Route::post('/',  [MessageController::class, 'store']);
        Route::delete('/{id}', [MessageController::class, 'destroy']);
    });

    // ============================================================
    // 3. ADMIN ONLY
    // ============================================================
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Dashboard stats
        Route::get('/dashboard',                          [DashboardController::class, 'index']);
        Route::get('/dashboard/revenue-chart',            [DashboardController::class, 'revenueChart']);
        Route::get('/dashboard/appointment-chart',        [DashboardController::class, 'appointmentChart']);
        Route::get('/dashboard/recent-activity',          [DashboardController::class, 'recentActivity']);
        Route::get('/dashboard/top-rnds',                 [DashboardController::class, 'topRnds']);

        // User management
        Route::get('/users',                      [AdminUserController::class, 'index']);
        Route::get('/users/{id}',                 [AdminUserController::class, 'show']);
        Route::patch('/users/{id}/toggle-active', [AdminUserController::class, 'toggleActive']);
        Route::patch('/users/{id}/verify-rnd',    [AdminUserController::class, 'verifyRnd']);
        Route::delete('/users/{id}',              [AdminUserController::class, 'destroy']);

        // Resources (platform-wide oversight + admin's own uploads)
        Route::get('/resources',          [AdminResourceController::class, 'index']);
        Route::get('/resources/{id}',     [AdminResourceController::class, 'show']);
        Route::post('/resources',         [AdminResourceController::class, 'store']);
        Route::put('/resources/{id}',     [AdminResourceController::class, 'update']);
        Route::delete('/resources/{id}',  [AdminResourceController::class, 'destroy']);
    });

    // ============================================================
    // 4. RND ONLY
    // ============================================================
    Route::middleware('role:rnd')->prefix('rnd')->group(function () {
        // Appointments
        Route::get('/appointments',               [RndAppointmentController::class, 'index']);
        Route::get('/appointments/{id}',          [RndAppointmentController::class, 'show']);
        Route::patch('/appointments/{id}/confirm',[RndAppointmentController::class, 'confirm']);
        Route::patch('/appointments/{id}/complete',[RndAppointmentController::class, 'complete']);
        Route::patch('/appointments/{id}/cancel', [RndAppointmentController::class, 'cancel']);

        // Relationship Acceptance
        Route::get('/relationships',                  [RndRelationshipController::class, 'index']);
        Route::get('/relationships/{id}',              [RndRelationshipController::class, 'show']);
        Route::patch('/relationships/{id}/accept',     [RndRelationshipController::class, 'accept']);
        Route::patch('/relationships/{id}/decline',    [RndRelationshipController::class, 'decline']);
        Route::patch('/relationships/{id}/discharge',  [RndRelationshipController::class, 'discharge']);

        // NCP Records
        Route::get('/relationships/{relationshipId}/ncp',  [NcpController::class, 'index']);
        Route::get('/ncp/{id}',                            [NcpController::class, 'show']);
        Route::post('/ncp',                                [NcpController::class, 'store']);
        Route::put('/ncp/{id}',                            [NcpController::class, 'update']);
        Route::patch('/ncp/{id}/finalize',                 [NcpController::class, 'finalize']);

        // Progress Records
        Route::get('/relationships/{relationshipId}/progress',         [RndProgressController::class, 'index']);
        Route::get('/relationships/{relationshipId}/progress/summary', [RndProgressController::class, 'summary']);
        Route::post('/relationships/{relationshipId}/progress',        [RndProgressController::class, 'store']);
        Route::get('/progress/{id}',                                   [RndProgressController::class, 'show']);
        Route::put('/progress/{id}',                                   [RndProgressController::class, 'update']);
        Route::delete('/progress/{id}',                                [RndProgressController::class, 'destroy']);

        // Availability Schedule
        Route::get('/availability',              [AvailabilityController::class, 'index']);
        Route::post('/availability',             [AvailabilityController::class, 'store']);
        Route::put('/availability/{id}',         [AvailabilityController::class, 'update']);
        Route::delete('/availability/{id}',      [AvailabilityController::class, 'destroy']);
        Route::post('/availability/block-day',   [AvailabilityController::class, 'blockDay']);

        // Meal Plans
        Route::get('/meal-plans',                  [MealPlanController::class, 'index']);
        Route::get('/meal-plans/{id}',              [MealPlanController::class, 'show']);
        Route::post('/meal-plans',                  [MealPlanController::class, 'store']);
        Route::put('/meal-plans/{id}',               [MealPlanController::class, 'update']);
        Route::delete('/meal-plans/{id}',            [MealPlanController::class, 'destroy']);

        // Meal Plan — Meal Slots
        Route::get('/meal-plans/{mealPlanId}/meals',              [MealPlanMealController::class, 'index']);
        Route::get('/meal-plans/{mealPlanId}/meals/{id}',          [MealPlanMealController::class, 'show']);
        Route::post('/meal-plans/{mealPlanId}/meals',              [MealPlanMealController::class, 'store']);
        Route::put('/meal-plans/{mealPlanId}/meals/{id}',           [MealPlanMealController::class, 'update']);
        Route::delete('/meal-plans/{mealPlanId}/meals/{id}',        [MealPlanMealController::class, 'destroy']);

        // Meal Plan — Meal Slot Food Items
        Route::get('/meal-plans/{mealPlanId}/meals/{mealId}/food-items',          [MealPlanFoodItemController::class, 'index']);
        Route::get('/meal-plans/{mealPlanId}/meals/{mealId}/food-items/{id}',      [MealPlanFoodItemController::class, 'show']);
        Route::post('/meal-plans/{mealPlanId}/meals/{mealId}/food-items',          [MealPlanFoodItemController::class, 'store']);
        Route::put('/meal-plans/{mealPlanId}/meals/{mealId}/food-items/{id}',       [MealPlanFoodItemController::class, 'update']);
        Route::delete('/meal-plans/{mealPlanId}/meals/{mealId}/food-items/{id}',    [MealPlanFoodItemController::class, 'destroy']);

        // Resources
        Route::get('/resources',          [RndResourceController::class, 'index']);
        Route::get('/resources/{id}',     [RndResourceController::class, 'show']);
        Route::post('/resources',         [RndResourceController::class, 'store']);
        Route::put('/resources/{id}',     [RndResourceController::class, 'update']);
        Route::delete('/resources/{id}',  [RndResourceController::class, 'destroy']);

        // Reminders (only for clients in an active relationship with this RND)
        Route::get('/reminders',          [RndReminderController::class, 'index']);
        Route::get('/reminders/{id}',     [RndReminderController::class, 'show']);
        Route::post('/reminders',         [RndReminderController::class, 'store']);
        Route::put('/reminders/{id}',     [RndReminderController::class, 'update']);
        Route::delete('/reminders/{id}',  [RndReminderController::class, 'destroy']);
    });

    // ============================================================
    // 5. CLIENT ONLY
    // ============================================================
    Route::middleware('role:client')->prefix('client')->group(function () {
        // RND discovery and matching
        Route::get('/rnds',                          [RndMatchController::class, 'search']);
        Route::get('/rnds/{rndId}',                  [RndMatchController::class, 'show']);
        Route::post('/rnds/{rndId}/request',         [RndMatchController::class, 'requestRelationship']);

        // Relationships (read-only)
        Route::get('/relationships',                 [ClientRelationshipController::class, 'index']);
        Route::get('/relationships/{id}',            [ClientRelationshipController::class, 'show']);

        // RND availability (client reads RND's schedule for booking)
        Route::get('/rnds/{rndId}/availability',     [AvailabilityController::class, 'active']);

        // Appointments
        Route::get('/appointments',                  [ClientAppointmentController::class, 'index']);
        Route::get('/appointments/{id}',             [ClientAppointmentController::class, 'show']);
        Route::post('/appointments',                 [ClientAppointmentController::class, 'store']);
        Route::patch('/appointments/{id}/cancel',    [ClientAppointmentController::class, 'cancel']);

        // Reviews
        Route::post('/appointments/{appointmentId}/review',  [ReviewController::class, 'store']);
        Route::get('/appointments/{appointmentId}/review',   [ReviewController::class, 'myReview']);
        Route::put('/reviews/{reviewId}',                    [ReviewController::class, 'update']);
        Route::delete('/reviews/{reviewId}',                 [ReviewController::class, 'destroy']);
        Route::get('/rnds/{rndId}/reviews',                  [ReviewController::class, 'rndReviews']);

        // Pre-consultation screening
        Route::post('/screening',                    [PreConsultationScreeningController::class, 'store']);
        Route::get('/screening/{appointmentId}',     [PreConsultationScreeningController::class, 'show']);

        // Progress Records (read-only for client)
        Route::get('/progress',                                               [ClientProgressController::class, 'myProgress']);
        Route::get('/relationships/{relationshipId}/progress',                [ClientProgressController::class, 'index']);

        // Resources (read-only — visibility derived from RND relationships + admin uploads)
        Route::get('/resources',       [ClientResourceController::class, 'index']);
        Route::get('/resources/{id}',  [ClientResourceController::class, 'show']);

        // Reminders (self-managed)
        Route::get('/reminders',          [ClientReminderController::class, 'index']);
        Route::get('/reminders/{id}',     [ClientReminderController::class, 'show']);
        Route::post('/reminders',         [ClientReminderController::class, 'store']);
        Route::put('/reminders/{id}',     [ClientReminderController::class, 'update']);
        Route::delete('/reminders/{id}',  [ClientReminderController::class, 'destroy']);

        // Payments
        Route::post('/invoices/{invoiceId}/pay',     [PaymentController::class, 'initiatePayment']);
    });
});
