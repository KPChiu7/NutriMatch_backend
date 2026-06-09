<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Client\AppointmentController as ClientAppointmentController;
use App\Http\Controllers\Api\Client\PaymentController;
use App\Http\Controllers\Api\Client\PreConsultationScreeningController;
use App\Http\Controllers\Api\Client\RndMatchController;
use App\Http\Controllers\Api\Rnd\AppointmentController as RndAppointmentController;
use App\Http\Controllers\Api\Rnd\NcpController;
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
        // User management
        Route::get('/users',                      [AdminUserController::class, 'index']);
        Route::get('/users/{id}',                 [AdminUserController::class, 'show']);
        Route::patch('/users/{id}/toggle-active', [AdminUserController::class, 'toggleActive']);
        Route::patch('/users/{id}/verify-rnd',    [AdminUserController::class, 'verifyRnd']);
        Route::delete('/users/{id}',              [AdminUserController::class, 'destroy']);
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

        // NCP Records
        Route::get('/relationships/{relationshipId}/ncp',  [NcpController::class, 'index']);
        Route::get('/ncp/{id}',                            [NcpController::class, 'show']);
        Route::post('/ncp',                                [NcpController::class, 'store']);
        Route::put('/ncp/{id}',                            [NcpController::class, 'update']);
        Route::patch('/ncp/{id}/finalize',                 [NcpController::class, 'finalize']);
    });

    // ============================================================
    // 5. CLIENT ONLY
    // ============================================================
    Route::middleware('role:client')->prefix('client')->group(function () {
        // RND discovery and matching
        Route::get('/rnds',                          [RndMatchController::class, 'search']);
        Route::get('/rnds/{rndId}',                  [RndMatchController::class, 'show']);
        Route::post('/rnds/{rndId}/request',         [RndMatchController::class, 'requestRelationship']);

        // Appointments
        Route::get('/appointments',                  [ClientAppointmentController::class, 'index']);
        Route::get('/appointments/{id}',             [ClientAppointmentController::class, 'show']);
        Route::post('/appointments',                 [ClientAppointmentController::class, 'store']);
        Route::patch('/appointments/{id}/cancel',    [ClientAppointmentController::class, 'cancel']);

        // Pre-consultation screening
        Route::post('/screening',                    [PreConsultationScreeningController::class, 'store']);
        Route::get('/screening/{appointmentId}',     [PreConsultationScreeningController::class, 'show']);

        // Payments
        Route::post('/invoices/{invoiceId}/pay',     [PaymentController::class, 'initiatePayment']);
    });
});
