<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\RedisStockCacheService;

class RedisCacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RedisStockCacheService::class, function ($app) {
            return new RedisStockCacheService();
        });
    }

    public function boot(): void
    {
        // Register Redis connections if they don't exist
        if (!app()->configurationIsCached()) {
            config([
                'database.redis.stock-cache' => [
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'password' => env('REDIS_PASSWORD'),
                    'port' => env('REDIS_PORT', 6379),
                    'database' => env('REDIS_STOCK_DB', 2),
                ]
            ]);
        }
    }
}
