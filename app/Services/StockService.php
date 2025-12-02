<?php
// app/Services/StockService.php

namespace App\Services;

use App\Models\Product;
use App\Models\StockHold;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockService
{
    private const HOLD_DURATION_MINUTES = 2;
    private const STOCK_LOCK_TIMEOUT = 10; // seconds
    private const STOCK_LOCK_PREFIX = 'product_stock_lock:';

    public function createHold(string $productId, int $quantity): ?StockHold
    {
        return DB::transaction(function () use ($productId, $quantity) {
            // Get pessimistic lock on product
            $product = Product::where('id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$product) {
                return null;
            }

            // Check available stock
            if ($product->available_stock < $quantity) {
                return null;
            }

            // Create hold
            $hold = StockHold::create([
                'id' => Str::uuid(),
                'product_id' => $productId,
                'quantity' => $quantity,
                'expires_at' => now()->addMinutes(self::HOLD_DURATION_MINUTES),
                'status' => 'active',
            ]);

            // Update available stock cache
            $product->updateAvailableStock();

            // Clear product cache
            Cache::forget("product:{$productId}");

            return $hold;
        }, 3); // 3 attempts for deadlock
    }

    public function releaseHold(string $holdId): void
    {
        DB::transaction(function () use ($holdId) {
            $hold = StockHold::where('id', $holdId)
                ->lockForUpdate()
                ->first();

            if ($hold && $hold->isActive()) {
                $hold->markAsExpired();
                $hold->product->updateAvailableStock();
                Cache::forget("product:{$hold->product_id}");
            }
        });
    }

    public function cleanupExpiredHolds(): void
    {
        $expiredHolds = StockHold::where('status', 'active')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredHolds as $hold) {
            $this->releaseHold($hold->id);
        }
    }

    public function getProductWithStock(string $productId): ?Product
    {
        return Cache::remember(
            "product:{$productId}",
            now()->addSeconds(5), // Short cache TTL for stock accuracy
            function () use ($productId) {
                return Product::find($productId);
            }
        );
    }
}
