<?php

namespace App\Repositories;

use App\Models\Product;
use App\Services\RedisStockCacheService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductRepository
{
    public function __construct(
        private RedisStockCacheService $cacheService
    ) {}

    public function getProductWithStock(int $productId): ?array
    {
        // Try to get from cache first
        $cachedProduct = $this->cacheService->getProductData($productId);
        
        if ($cachedProduct) {
            $cachedProduct['available_stock'] = $this->cacheService->getStockWithFallback(
                $productId,
                fn() => $this->calculateAvailableStock($productId)
            );
            
            return $cachedProduct;
        }

        // Cache miss - get from database and cache
        $product = Product::with(['activeHolds'])->find($productId);
        
        if (!$product) {
            return null;
        }

        $availableStock = $this->calculateAvailableStock($productId);
        
        $productData = [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'initial_stock' => $product->initial_stock,
            'available_stock' => $availableStock,
            'is_active' => $product->is_active,
        ];

        // Cache product data and stock
        $this->cacheService->cacheProductData($productId, $productData);
        $this->cacheService->cacheStock($productId, $availableStock);

        return $productData;
    }

    public function calculateAvailableStock(int $productId): int
    {
        $stock = DB::selectOne("
            SELECT 
                p.available_stock as current_stock,
                COALESCE(SUM(
                    CASE 
                        WHEN sh.status = 'pending' AND sh.expires_at > NOW() 
                        THEN sh.quantity 
                        ELSE 0 
                    END
                ), 0) as held_stock
            FROM products p
            LEFT JOIN stock_holds sh ON p.id = sh.product_id
            WHERE p.id = ?
            GROUP BY p.id, p.available_stock
        ", [$productId]);

        return max(0, ($stock->current_stock ?? 0) - ($stock->held_stock ?? 0));
    }

    public function reserveStock(int $productId, int $quantity): ?string
    {
        $lockKey = "product_reserve:{$productId}";
        
        if (!$this->cacheService->acquireLock($lockKey)) {
            Log::warning('Could not acquire lock for product reservation', [
                'product_id' => $productId,
                'quantity' => $quantity
            ]);
            return null;
        }

        try {
            DB::beginTransaction();

            // Get current stock with lock
            $product = Product::where('id', $productId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$product || $product->available_stock < $quantity) {
                DB::rollBack();
                return null;
            }

            // Decrement stock
            $product->decrement('available_stock', $quantity);

            // Create stock hold
            $holdId = (string) \Illuminate\Support\Str::uuid();
            $expiresAt = now()->addMinutes(2);

            DB::table('stock_holds')->insert([
                'id' => $holdId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'status' => 'pending',
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            // Update Redis cache
            $this->cacheService->decrementStock($productId, $quantity);
            $this->cacheService->cacheHold($holdId, [
                'product_id' => $productId,
                'quantity' => $quantity,
                'expires_at' => $expiresAt->toISOString(),
                'status' => 'pending'
            ]);

            return $holdId;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reserve stock', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'error' => $e->getMessage()
            ]);
            return null;
        } finally {
            $this->cacheService->releaseLock($lockKey);
        }
    }

    public function releaseStockHold(string $holdId): bool
    {
        $hold = $this->cacheService->getHold($holdId) 
            ?: DB::table('stock_holds')->where('id', $holdId)->first();

        if (!$hold) {
            return false;
        }

        $hold = (array) $hold;
        $lockKey = "product_release:{$hold['product_id']}";

        if (!$this->cacheService->acquireLock($lockKey)) {
            return false;
        }

        try {
            DB::beginTransaction();

            $updated = DB::table('stock_holds')
                ->where('id', $holdId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'expired',
                    'updated_at' => now()
                ]);

            if ($updated) {
                // Increment product stock
                DB::table('products')
                    ->where('id', $hold['product_id'])
                    ->increment('available_stock', $hold['quantity']);

                DB::commit();

                // Update Redis cache
                $this->cacheService->incrementStock($hold['product_id'], $hold['quantity']);
                $this->cacheService->deleteHold($holdId);

                return true;
            }

            DB::rollBack();
            return false;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to release stock hold', [
                'hold_id' => $holdId,
                'error' => $e->getMessage()
            ]);
            return false;
        } finally {
            $this->cacheService->releaseLock($lockKey);
        }
    }

    public function invalidateProductCache(int $productId): void
    {
        $this->cacheService->invalidateStockCache($productId);
        
        $productKey = 'product:data:' . $productId;
        Redis::connection('cache')->del($productKey);
    }
}
