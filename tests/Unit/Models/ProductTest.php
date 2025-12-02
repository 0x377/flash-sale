<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Product;
use App\Models\StockHold;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_calculate_available_stock(): void
    {
        $product = Product::factory()->create([
            'initial_stock' => 100,
            'available_stock' => 100,
        ]);

        // Create some active holds
        StockHold::factory()->count(3)->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        $availableStock = $product->calculateAvailableStock();

        $this->assertEquals(85, $availableStock); // 100 - (3 * 5) = 85
    }

    public function test_can_fulfill_quantity_check(): void
    {
        $product = Product::factory()->create([
            'available_stock' => 10,
        ]);

        $this->assertTrue($product->canFulfillQuantity(5));
        $this->assertFalse($product->canFulfillQuantity(15));
        $this->assertFalse($product->canFulfillQuantity(0));
        $this->assertFalse($product->canFulfillQuantity(-1));
    }

    public function test_stock_reservation_success(): void
    {
        $product = Product::factory()->create([
            'available_stock' => 10,
        ]);

        $hold = $product->reserveStock(3);

        $this->assertNotNull($hold);
        $this->assertEquals('pending', $hold->status);
        $this->assertEquals(3, $hold->quantity);
        $this->assertTrue($hold->expires_at->isFuture());
    }

    public function test_stock_reservation_fails_when_insufficient_stock(): void
    {
        $product = Product::factory()->create([
            'available_stock' => 2,
        ]);

        $hold = $product->reserveStock(5);

        $this->assertNull($hold);
    }

    public function test_stock_increment_and_decrement(): void
    {
        $product = Product::factory()->create([
            'available_stock' => 10,
        ]);

        $this->assertTrue($product->incrementStock(5));
        $this->assertEquals(15, $product->fresh()->available_stock);

        $this->assertTrue($product->decrementStock(3));
        $this->assertEquals(12, $product->fresh()->available_stock);

        $this->assertFalse($product->decrementStock(20)); // Insufficient stock
        $this->assertEquals(12, $product->fresh()->available_stock);
    }

    public function test_stock_metrics_calculation(): void
    {
        $product = Product::factory()->create([
            'initial_stock' => 100,
            'available_stock' => 50,
        ]);

        // Create various holds
        StockHold::factory()->count(2)->create([
            'product_id' => $product->id,
            'status' => 'pending',
            'quantity' => 5,
        ]);

        StockHold::factory()->count(3)->create([
            'product_id' => $product->id,
            'status' => 'expired',
            'quantity' => 2,
        ]);

        StockHold::factory()->count(1)->create([
            'product_id' => $product->id,
            'status' => 'consumed',
            'quantity' => 10,
        ]);

        $metrics = $product->getStockMetrics();

        $this->assertEquals(6, $metrics['total_holds']);
        $this->assertEquals(2, $metrics['active_holds']);
        $this->assertEquals(3, $metrics['expired_holds']);
        $this->assertEquals(1, $metrics['consumed_holds']);
        $this->assertEquals(50, $metrics['stock_utilization']); // (100-50)/100 * 100 = 50%
    }
}
