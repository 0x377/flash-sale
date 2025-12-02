<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear all caches before each test
        Cache::flush();
        Redis::flushall();
        
        // Start transaction for database tests
        if (method_exists($this, 'beginDatabaseTransaction')) {
            $this->beginDatabaseTransaction();
        }
    }

    protected function tearDown(): void
    {
        // Rollback transaction
        if (method_exists($this, 'rollbackDatabaseTransaction')) {
            $this->rollbackDatabaseTransaction();
        }
        
        parent::tearDown();
    }

    protected function runConcurrently(callable $task, int $concurrency = 10): array
    {
        $promises = [];
        
        for ($i = 0; $i < $concurrency; $i++) {
            $promises[] = async(function () use ($task, $i) {
                return $task($i);
            });
        }
        
        return wait($promises);
    }

    protected function assertNoOverselling(int $productId, int $initialStock): void
    {
        $totalSold = DB::table('orders')
            ->where('product_id', $productId)
            ->where('status', 'paid')
            ->sum('quantity');
            
        $totalHolds = DB::table('stock_holds')
            ->where('product_id', $productId)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->sum('quantity');
            
        $currentStock = DB::table('products')
            ->where('id', $productId)
            ->value('available_stock');
            
        $this->assertLessThanOrEqual(
            $initialStock, 
            $totalSold + $totalHolds + $currentStock,
            "Overselling detected! Initial: {$initialStock}, Sold: {$totalSold}, Held: {$totalHolds}, Current: {$currentStock}"
        );
    }

    protected function generateIdempotencyKey(): string
    {
        return 'test_key_' . uniqid() . '_' . time();
    }
}
