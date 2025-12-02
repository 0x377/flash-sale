<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    ProductController,
    StockHoldController,
    OrderController,
    PaymentWebhookController
};

/*
|-----------------
| API Routes
|-----------------
*/

// Product Endpoint
Route::get('/products/{id}', [ProductController::class, 'show'])
    ->name('products.show');

// Create Hold
Route::post('/holds', [StockHoldController::class, 'store'])
    ->name('holds.store');

// Create Order
Route::post('/orders', [OrderController::class, 'store'])
    ->name('orders.store');

// Payment Webhook (Idempotent)
Route::post('/payments/webhook', [PaymentWebhookController::class, 'handle'])
    ->name('payments.webhook');

// Optional: Admin/Dev endpoints for testing and monitoring
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/holds/expired', [HoldController::class, 'expiredHolds']);
    Route::get('/metrics/contention', [HoldController::class, 'contentionMetrics']);
});
