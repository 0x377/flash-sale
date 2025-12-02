<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\StockHold;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FlashSaleTestCase extends TestCase
{
    protected Product $flashSaleProduct;
    protected int $initialStock = 100;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a flash sale product for testing
        $this->flashSaleProduct = Product::factory()->create([
            'name' => 'Flash Sale Test Product',
            'price' => 99.99,
            'initial_stock' => $this->initialStock,
            'available_stock' => $this->initialStock,
            'is_active' => true,
        ]);

        Cache::put('flash_sale:active_product', $this->flashSaleProduct->id, 3600);
    }

    protected function createStockHold(int $quantity = 1, array $overrides = []): StockHold
    {
        return StockHold::factory()->create(array_merge([
            'product_id' => $this->flashSaleProduct->id,
            'quantity' => $quantity,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(2),
        ], $overrides));
    }

    protected function createOrderFromHold(StockHold $hold, array $overrides = []): Order
    {
        return Order::factory()->create(array_merge([
            'product_id' => $hold->product_id,
            'stock_hold_id' => $hold->id,
            'quantity' => $hold->quantity,
            'unit_price' => $this->flashSaleProduct->price,
            'total_amount' => $hold->quantity * $this->flashSaleProduct->price,
            'status' => 'pending',
            'customer_email' => 'test@example.com',
        ], $overrides));
    }

    protected function assertStockConsistency(int $productId, int $initialStock): void
    {
        DB::transaction(function () use ($productId, $initialStock) {
            $product = Product::lockForUpdate()->find($productId);
            
            $totalSold = Order::where('product_id', $productId)
                ->where('status', 'paid')
                ->sum('quantity');
                
            $activeHolds = StockHold::where('product_id', $productId)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->sum('quantity');
                
            $expectedAvailable = $initialStock - $totalSold - $activeHolds;
            
            $this->assertEquals(
                $expectedAvailable,
                $product->available_stock,
                "Stock consistency check failed. Expected available: {$expectedAvailable}, Actual: {$product->available_stock}"
            );
        });
    }
}
