<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockHold;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockHoldSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔒 Seeding stock holds...');

        $products = Product::active()->get();
        
        if ($products->isEmpty()) {
            $this->command->warn('⚠️  No active products found. Skipping stock hold seeding.');
            return;
        }

        $holdTypes = [
            'active' => ['count' => 15, 'status' => 'pending', 'minutes' => [1, 10]],
            'expired' => ['count' => 8, 'status' => 'expired', 'minutes' => [-30, -5]],
            'consumed' => ['count' => 12, 'status' => 'consumed', 'minutes' => [-120, -10]],
            'expiring_soon' => ['count' => 5, 'status' => 'pending', 'minutes' => [0.5, 2]],
        ];

        $totalHolds = 0;

        foreach ($holdTypes as $type => $config) {
            $this->command->info("   Creating {$type} holds...");

            for ($i = 0; $i < $config['count']; $i++) {
                $product = $products->random();
                $quantity = rand(1, min(3, $product->available_stock));

                $holdData = [
                    'id' => (string) Str::uuid(),
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'session_id' => "session_" . Str::random(10),
                    'status' => $config['status'],
                    'expires_at' => now()->addMinutes(rand(
                        $config['minutes'][0] * 60, 
                        $config['minutes'][1] * 60
                    )),
                    'created_at' => now()->subMinutes(rand(1, 60)),
                    'updated_at' => now(),
                ];

                if ($config['status'] === 'consumed') {
                    $holdData['consumed_at'] = now()->subMinutes(rand(5, 60));
                }

                StockHold::create($holdData);
                $totalHolds++;

                // Update product available stock for active holds
                if ($config['status'] === 'pending' && $holdData['expires_at']->isFuture()) {
                    $product->decrement('available_stock', $quantity);
                }
            }
        }

        // Create some concurrent hold scenarios for testing
        $this->createConcurrentHoldScenarios($products->first());

        $this->command->info("📋 Created {$totalHolds} stock holds of various types");
        $this->command->info('⚡ Created concurrent hold scenarios for stress testing');
    }

    private function createConcurrentHoldScenarios(Product $product): void
    {
        $this->command->info('   Creating concurrent hold scenarios...');

        $scenarios = [
            'high_demand' => [
                'count' => 20,
                'quantity' => 1,
                'time_window' => 10, // seconds
            ],
            'bulk_orders' => [
                'count' => 8,
                'quantity' => 2,
                'time_window' => 30,
            ],
            'mixed_load' => [
                'count' => 15,
                'quantity' => [1, 2],
                'time_window' => 15,
            ]
        ];

        foreach ($scenarios as $scenarioName => $config) {
            $baseTime = now()->subSeconds($config['time_window']);
            
            for ($i = 0; $i < $config['count']; $i++) {
                $quantity = is_array($config['quantity']) 
                    ? $config['quantity'][array_rand($config['quantity'])]
                    : $config['quantity'];

                StockHold::create([
                    'id' => (string) Str::uuid(),
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'session_id' => "stress_test_{$scenarioName}_" . Str::random(6),
                    'status' => 'pending',
                    'expires_at' => now()->addMinutes(2),
                    'created_at' => $baseTime->addMilliseconds(rand(1, 500)),
                    'updated_at' => now(),
                ]);

                // Update product stock
                $product->decrement('available_stock', $quantity);
            }

            $this->command->info("     ✅ {$scenarioName}: {$config['count']} holds");
        }
    }
}
