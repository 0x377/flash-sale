<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\StockHold;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class StockHoldTest extends TestCase
{
    use RefreshDatabase;

    public function test_hold_expiration_check(): void
    {
        $activeHold = StockHold::factory()->create([
            'expires_at' => now()->addMinutes(10),
        ]);

        $expiredHold = StockHold::factory()->create([
            'expires_at' => now()->subMinutes(10),
        ]);

        $this->assertFalse($activeHold->is_expired);
        $this->assertTrue($expiredHold->is_expired);
    }

    public function test_hold_active_status(): void
    {
        $activeHold = StockHold::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        $expiredHold = StockHold::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->subMinutes(10),
        ]);

        $consumedHold = StockHold::factory()->create([
            'status' => 'consumed',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->assertTrue($activeHold->is_active);
        $this->assertFalse($expiredHold->is_active);
        $this->assertFalse($consumedHold->is_active);
    }

    public function test_mark_hold_as_consumed(): void
    {
        $hold = StockHold::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        $result = $hold->markAsConsumed();

        $this->assertTrue($result);
        $this->assertEquals('consumed', $hold->fresh()->status);
        $this->assertNotNull($hold->fresh()->consumed_at);
    }

    public function test_cannot_consume_expired_hold(): void
    {
        $hold = StockHold::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->subMinutes(10),
        ]);

        $result = $hold->markAsConsumed();

        $this->assertFalse($result);
        $this->assertEquals('pending', $hold->fresh()->status);
    }

    public function test_hold_release_stock(): void
    {
        $product = Product::factory()->create(['available_stock' => 10]);
        $hold = StockHold::factory()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'status' => 'pending',
        ]);

        $result = $hold->releaseStock();

        $this->assertTrue($result);
        $this->assertEquals('expired', $hold->fresh()->status);
        $this->assertEquals(10, $product->fresh()->available_stock); // Stock should be returned
    }

    public function test_hold_renewal(): void
    {
        $hold = StockHold::factory()->create([
            'expires_at' => now()->addMinutes(1),
        ]);

        $originalExpiry = $hold->expires_at;
        $result = $hold->renew(5); // Add 5 minutes

        $this->assertTrue($result);
        $this->assertTrue($hold->fresh()->expires_at->gt($originalExpiry));
    }

    public function test_auto_expire_old_holds(): void
    {
        StockHold::factory()->count(5)->create([
            'status' => 'pending',
            'expires_at' => now()->subMinutes(10),
        ]);

        StockHold::factory()->count(3)->create([
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        $expiredCount = StockHold::expireOldHolds();

        $this->assertEquals(5, $expiredCount);
        $this->assertEquals(5, StockHold::where('status', 'expired')->count());
        $this->assertEquals(3, StockHold::where('status', 'pending')->count());
    }

    public function test_hold_validity_for_order_creation(): void
    {
        $validHold = StockHold::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        $expiredHold = StockHold::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->subMinutes(10),
        ]);

        $consumedHold = StockHold::factory()->create([
            'status' => 'consumed',
            'expires_at' => now()->addMinutes(10),
        ]);

        $holdWithOrder = StockHold::factory()->create([
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);
        Order::factory()->create(['stock_hold_id' => $holdWithOrder->id]);

        $this->assertTrue($validHold->isValidForOrder());
        $this->assertFalse($expiredHold->isValidForOrder());
        $this->assertFalse($consumedHold->isValidForOrder());
        $this->assertFalse($holdWithOrder->isValidForOrder());
    }
}
