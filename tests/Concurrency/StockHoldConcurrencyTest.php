<?php

namespace Tests\Concurrency;

use Tests\Feature\FlashSaleTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockHoldConcurrencyTest extends FlashSaleTestCase
{
    public function test_no_overselling_under_high_concurrency(): void
    {
        $concurrentRequests = 50;
        $initialStock = 10;
        
        $this->flashSaleProduct->update(['available_stock' => $initialStock]);

        $results = $this->runConcurrently(function ($i) {
            try {
                $response = $this->postJson('/api/holds', [
                    'product_id' => $this->flashSaleProduct->id,
                    'quantity' => 1,
                    'session_id' => "concurrent_session_{$i}",
                ]);

                return [
                    'success' => $response->getStatusCode() === 201,
                    'status' => $response->getStatusCode(),
                    'data' => $response->json(),
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }, $concurrentRequests);

        $successfulHolds = collect($results)->filter(fn($r) => $r['success'])->count();
        $failedHolds = $concurrentRequests - $successfulHolds;

        Log::info('Concurrency test results', [
            'concurrent_requests' => $concurrentRequests,
            'initial_stock' => $initialStock,
            'successful_holds' => $successfulHolds,
            'failed_holds' => $failedHolds,
        ]);

        // Assert no overselling occurred
        $this->assertNoOverselling($this->flashSaleProduct->id, $initialStock);
        
        // Should have exactly initialStock successful holds
        $this->assertEquals($initialStock, $successfulHolds);
        $this->assertEquals($concurrentRequests - $initialStock, $failedHolds);

        // Verify stock consistency
        $this->assertStockConsistency($this->flashSaleProduct->id, $initialStock);
    }

    public function test_concurrent_holds_with_varying_quantities(): void
    {
        $initialStock = 20;
        $this->flashSaleProduct->update(['available_stock' => $initialStock]);

        $requests = [];
        for ($i = 0; $i < 15; $i++) {
            $quantity = $i % 3 + 1; // 1, 2, or 3
            $requests[] = [
                'product_id' => $this->flashSaleProduct->id,
                'quantity' => $quantity,
                'session_id' => "var_qty_session_{$i}",
            ];
        }

        $results = $this->runConcurrently(function ($i) use ($requests) {
            $response = $this->postJson('/api/holds', $requests[$i]);
            return [
                'success' => $response->getStatusCode() === 201,
                'quantity' => $requests[$i]['quantity'],
                'status' => $response->getStatusCode(),
            ];
        }, count($requests));

        $totalReserved = collect($results)
            ->filter(fn($r) => $r['success'])
            ->sum('quantity');

        $this->assertLessThanOrEqual($initialStock, $totalReserved);
        $this->assertNoOverselling($this->flashSaleProduct->id, $initialStock);
    }

    public function test_race_condition_on_stock_boundary(): void
    {
        // Set stock to exactly 1 to test boundary condition
        $this->flashSaleProduct->update(['available_stock' => 1]);

        $results = $this->runConcurrently(function ($i) {
            $response = $this->postJson('/api/holds', [
                'product_id' => $this->flashSaleProduct->id,
                'quantity' => 1,
                'session_id' => "race_session_{$i}",
            ]);

            return $response->getStatusCode() === 201;
        }, 10);

        $successCount = collect($results)->filter()->count();

        // Only one request should succeed
        $this->assertEquals(1, $successCount);
        $this->assertEquals(0, $this->flashSaleProduct->fresh()->available_stock);
        $this->assertNoOverselling($this->flashSaleProduct->id, 1);
    }

    public function test_concurrent_hold_and_order_creation(): void
    {
        $initialStock = 5;
        $this->flashSaleProduct->update(['available_stock' => $initialStock]);

        // Create some holds first
        $holds = [];
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/holds', [
                'product_id' => $this->flashSaleProduct->id,
                'quantity' => 1,
                'session_id' => "mixed_session_{$i}",
            ]);
            
            if ($response->getStatusCode() === 201) {
                $holds[] = $response->json('data.hold_id');
            }
        }

        // Try to create orders concurrently while also creating new holds
        $results = $this->runConcurrently(function ($i) use ($holds) {
            if ($i < count($holds)) {
                // Try to create order from existing hold
                $response = $this->postJson('/api/orders', [
                    'hold_id' => $holds[$i],
                    'customer_email' => "user{$i}@example.com",
                ]);
            } else {
                // Try to create new hold
                $response = $this->postJson('/api/holds', [
                    'product_id' => $this->flashSaleProduct->id,
                    'quantity' => 1,
                    'session_id' => "mixed_new_session_{$i}",
                ]);
            }

            return [
                'type' => $i < count($holds) ? 'order' : 'hold',
                'success' => $response->getStatusCode() === 201,
                'status' => $response->getStatusCode(),
            ];
        }, 10);

        $this->assertNoOverselling($this->flashSaleProduct->id, $initialStock);
        $this->assertStockConsistency($this->flashSaleProduct->id, $initialStock);
    }
}
