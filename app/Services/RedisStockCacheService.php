<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RedisStockCacheService
{
    private const STOCK_KEY_PREFIX = 'product:stock:';
    private const HOLD_KEY_PREFIX = 'product:hold:';
    private const PRODUCT_KEY_PREFIX = 'product:data:';
    private const LOCK_KEY_PREFIX = 'lock:product:';
    private const STOCK_UPDATE_CHANNEL = 'stock_updates';

    public function getAvailableStock(int $productId): ?int
    {
        $key = self::STOCK_KEY_PREFIX . $productId;

        // Try to get from Redis cache first
        $cachedStock = Redis::connection('stock-cache')->get($key);
        if ($cachedStock !== null) {
            return (int) $cachedStock;
        }

        // Cache miss - we'll warm the cache in the repository layer
        return null;
    }

    public function cacheStock(int $productId, int $availableStock, int $ttl = 60): void
    {
        $key = self::STOCK_KEY_PREFIX . $productId;
        
        Redis::connection('stock-cache')->setex(
            $key, 
            $ttl, 
            $availableStock
        );

        // Publish stock update for real-time applications
        $this->publishStockUpdate($productId, $availableStock);
    }

    public function decrementStock(int $productId, int $quantity): bool
    {
        $key = self::STOCK_KEY_PREFIX . $productId;
        
        $result = Redis::connection('stock-cache')->eval(
            <<<'LUA'
            local key = KEYS[1]
            local quantity = tonumber(ARGV[1])
            local current = redis.call('GET', key)
            
            if not current then
                return -1 -- Cache miss
            end
            
            current = tonumber(current)
            if current >= quantity then
                return redis.call('DECRBY', key, quantity)
            else
                return -2 -- Insufficient stock
            end
LUA,
            1, // Number of keys
            $key,
            $quantity
        );

        if ($result >= 0) {
            $this->publishStockUpdate($productId, $result);
            return true;
        }

        return false;
    }

    public function incrementStock(int $productId, int $quantity): int
    {
        $key = self::STOCK_KEY_PREFIX . $productId;
        
        $newStock = Redis::connection('stock-cache')->incrby($key, $quantity);
        
        $this->publishStockUpdate($productId, $newStock);
        
        return $newStock;
    }

    public function cacheProductData(int $productId, array $productData, int $ttl = 300): void
    {
        $key = self::PRODUCT_KEY_PREFIX . $productId;
        
        Redis::connection('cache')->setex(
            $key,
            $ttl,
            json_encode($productData)
        );
    }

    public function getProductData(int $productId): ?array
    {
        $key = self::PRODUCT_KEY_PREFIX . $productId;
        $data = Redis::connection('cache')->get($key);
        
        return $data ? json_decode($data, true) : null;
    }

    public function cacheHold(string $holdId, array $holdData, int $ttl = 120): void
    {
        $key = self::HOLD_KEY_PREFIX . $holdId;
        
        Redis::connection('cache')->setex(
            $key,
            $ttl,
            json_encode($holdData)
        );
    }

    public function getHold(string $holdId): ?array
    {
        $key = self::HOLD_KEY_PREFIX . $holdId;
        $data = Redis::connection('cache')->get($key);
        
        return $data ? json_decode($data, true) : null;
    }

    public function deleteHold(string $holdId): void
    {
        $key = self::HOLD_KEY_PREFIX . $holdId;
        Redis::connection('cache')->del($key);
    }

    public function acquireLock(string $lockKey, int $timeout = 10): bool
    {
        $key = self::LOCK_KEY_PREFIX . $lockKey;
        
        return Redis::connection('locks')->set(
            $key, 
            microtime(true), 
            'NX', 
            'EX', 
            $timeout
        );
    }

    public function releaseLock(string $lockKey): void
    {
        $key = self::LOCK_KEY_PREFIX . $lockKey;
        Redis::connection('locks')->del($key);
    }

    public function getLock(string $lockKey): ?float
    {
        $key = self::LOCK_KEY_PREFIX . $lockKey;
        $value = Redis::connection('locks')->get($key);
        
        return $value ? (float) $value : null;
    }

    private function publishStockUpdate(int $productId, int $availableStock): void
    {
        Redis::connection('stock-cache')->publish(
            self::STOCK_UPDATE_CHANNEL,
            json_encode([
                'product_id' => $productId,
                'available_stock' => $availableStock,
                'timestamp' => microtime(true)
            ])
        );
    }

    public function subscribeToStockUpdates(callable $callback): void
    {
        Redis::connection('stock-cache')->subscribe(
            [self::STOCK_UPDATE_CHANNEL], 
            function ($message) use ($callback) {
                $data = json_decode($message, true);
                $callback($data);
            }
        );
    }

    public function getStockWithFallback(int $productId, callable $fallback): int
    {
        $cachedStock = $this->getAvailableStock($productId);
        
        if ($cachedStock !== null) {
            return $cachedStock;
        }

        // Use lock to prevent cache stampede
        $lockKey = "stock_fallback:{$productId}";
        
        if ($this->acquireLock($lockKey, 5)) {
            try {
                $stock = $fallback();
                $this->cacheStock($productId, $stock);
                return $stock;
            } finally {
                $this->releaseLock($lockKey);
            }
        }

        // If we can't acquire lock, wait briefly and retry cached version
        usleep(100000); // 100ms
        $cachedStock = $this->getAvailableStock($productId);
        
        return $cachedStock ?? $fallback();
    }

    public function invalidateStockCache(int $productId): void
    {
        $key = self::STOCK_KEY_PREFIX . $productId;
        Redis::connection('stock-cache')->del($key);
    }

    public function getCacheStats(): array
    {
        $cacheConn = Redis::connection('cache');
        $stockConn = Redis::connection('stock-cache');
        
        return [
            'cache_connections' => $cacheConn->info('clients')['connected_clients'] ?? 0,
            'stock_connections' => $stockConn->info('clients')['connected_clients'] ?? 0,
            'cache_memory' => $cacheConn->info('memory')['used_memory'] ?? 0,
            'stock_memory' => $stockConn->info('memory')['used_memory'] ?? 0,
            'cache_keys' => $cacheConn->dbsize(),
            'stock_keys' => $stockConn->dbsize(),
        ];
    }
}
