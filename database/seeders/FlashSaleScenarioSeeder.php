<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockHold;
use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FlashSaleScenarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎪 Seeding flash sale scenarios...');

        $product = Product::active()->first();
        
        if (!$product) {
            $this->command->warn('⚠️  No active product found for flash sale scenarios.');
            return;
        }

        $this->createFlashSaleScenarios($product);
        $this->createPerformanceBaselines($product);
        $this->createTestScenarios($product);

        $this->command->info('🚀 Flash sale scenarios created successfully!');
        $this->command->info('📚 Test data ready for:');
        $this->command->info('   - Concurrency testing');
        $this->command->info('   - Stock reservation testing');
        $this->command->info('   - Idempotency testing');
        $this->command->info('   - Webhook processing testing');
    }

    private function createFlashSaleScenarios(Product $product): void
    {
        $this->command->info('   Creating flash sale scenarios...');

        $scenarios = [
            'high_traffic' => [
                'description' => 'Simulates high traffic with many concurrent hold requests',
                'initial_stock' => 50,
                'concurrent_users' => 100,
                'expected_success_rate' => 85,
            ],
            'limited_stock' => [
                'description' => 'Very limited stock with high demand',
                'initial_stock' => 5,
                'concurrent_users' => 50,
                'expected_success_rate' => 10,
            ],
            'bulk_purchases' => [
                'description' => 'Users attempting to purchase multiple items',
                'initial_stock' => 20,
                'concurrent_users' => 30,
                'max_quantity' => 3,
            ],
            'slow_network' => [
                'description' => 'Simulates slow network conditions with timeouts',
                'initial_stock' => 30,
                'concurrent_users' => 40,
                'timeout_rate' => 20,
            ]
        ];

        Cache::put('flash_sale:scenarios', $scenarios, 86400);

        // Update product stock for scenario testing
        $product->update([
            'initial_stock' => 100,
            'available_stock' => 100,
        ]);

        $this->command->info('     ✅ Created 4 flash sale scenarios');
    }

    private function createPerformanceBaselines(Product $product): void
    {
        $this->command->info('   Creating performance baselines...');

        $baselines = [
            'response_times' => [
                'product_endpoint' => ['p50' => 50, 'p95' => 150, 'p99' => 300],
                'hold_creation' => ['p50' => 100, 'p95' => 250, 'p99' => 500],
                'order_creation' => ['p50' => 80, 'p95' => 200, 'p99' => 400],
                'webhook_processing' => ['p50' => 60, 'p95' => 180, 'p99' => 350],
            ],
            'throughput' => [
                'holds_per_second' => 50,
                'orders_per_second' => 30,
                'webhooks_per_second' => 100,
            ],
            'error_rates' => [
                'hold_failures' => 2.5,
                'order_failures' => 1.5,
                'webhook_failures' => 0.5,
            ],
            'concurrency_limits' => [
                'max_concurrent_holds' => 1000,
                'max_concurrent_orders' => 500,
                'max_concurrent_webhooks' => 2000,
            ]
        ];

        Cache::put('performance:baselines', $baselines, 86400);
        $this->command->info('     ✅ Created performance baselines');
    }

    private function createTestScenarios(Product $product): void
    {
        $this->command->info('   Creating specific test scenarios...');

        // Scenario 1: Race condition test
        $this->createRaceConditionScenario($product);

        // Scenario 2: Idempotency test
        $this->createIdempotencyTestScenario($product);

        // Scenario 3: Webhook out-of-order test
        $this->createOutOfOrderWebhookScenario($product);

        // Scenario 4: Stock exhaustion test
        $this->createStockExhaustionScenario($product);

        $this->command->info('     ✅ Created 4 specific test scenarios');
    }

    private function createRaceConditionScenario(Product $product): void
    {
        // Create multiple holds for the last few items
        $remainingStock = 3;
        $product->update(['available_stock' => $remainingStock]);

        $holdRequests = 10;
        
        $scenario = [
            'name' => 'race_condition_last_items',
            'product_id' => $product->id,
            'remaining_stock' => $remainingStock,
            'concurrent_requests' => $holdRequests,
            'expected_successful_holds' => $remainingStock,
            'expected_failed_holds' => $holdRequests - $remainingStock,
            'description' => 'Tests that only 3 holds succeed when 10 concurrent requests try to reserve the last 3 items'
        ];

        Cache::put('test:scenario:race_condition', $scenario, 86400);
    }

    private function createIdempotencyTestScenario(Product $product): void
    {
        $idempotencyKey = 'test_idempotency_' . Str::random(16);
        
        $scenario = [
            'name' => 'idempotency_test',
            'idempotency_key' => $idempotencyKey,
            'product_id' => $product->id,
            'expected_behavior' => 'Multiple requests with same idempotency key should return same response',
            'test_cases' => [
                'first_request' => 'Should process normally',
                'second_request' => 'Should return cached response',
                'different_parameters' => 'Should return error',
            ]
        ];

        Cache::put('test:scenario:idempotency', $scenario, 86400);
    }

    private function createOutOfOrderWebhookScenario(Product $product): void
    {
        // Create an order that will receive webhooks
        $order = Order::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'stock_hold_id' => null,
            'quantity' => 1,
            'unit_price' => $product->price,
            'total_amount' => $product->price,
            'status' => 'pending',
            'customer_email' => 'webhook_test@example.com',
            'customer_details' => ['name' => 'Webhook Test User'],
        ]);

        $scenario = [
            'name' => 'webhook_out_of_order',
            'order_id' => $order->id,
            'webhook_arrival_time' => $order->created_at->subSeconds(30)->toISOString(),
            'order_creation_time' => $order->created_at->toISOString(),
            'expected_behavior' => 'Webhook should be processed successfully even though it arrived before order creation',
            'test_steps' => [
                '1. Send webhook for non-existent order',
                '2. Create order with same ID',
                '3. Verify webhook is processed and order status updated',
            ]
        ];

        Cache::put('test:scenario:webhook_out_of_order', $scenario, 86400);
    }

    private function createStockExhaustionScenario(Product $product): void
    {
        // Set product to very low stock
        $product->update(['available_stock' => 1]);

        $scenario = [
            'name' => 'stock_exhaustion',
            'product_id' => $product->id,
            'initial_stock' => 1,
            'concurrent_requests' => 5,
            'expected_outcome' => 'Only one hold should succeed, others should fail with insufficient stock',
            'validation_checks' => [
                'total_holds_created' => 1,
                'product_available_stock' => 0,
                'failed_requests' => 4,
            ]
        ];

        Cache::put('test:scenario:stock_exhaustion', $scenario, 86400);

        // Reset stock for other tests
        $product->update(['available_stock' => 100]);
    }
}
