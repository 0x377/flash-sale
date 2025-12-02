<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\StockHold;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🛒 Seeding orders...');

        // Get consumed holds to create orders from
        $consumedHolds = StockHold::where('status', 'consumed')->get();

        $orderStatuses = [
            'paid' => 60, // 60% of orders
            'pending' => 25, // 25% of orders
            'failed' => 10, // 10% of orders
            'cancelled' => 5, // 5% of orders
        ];

        $createdCount = 0;
        $revenue = 0;

        // Create orders from consumed holds
        foreach ($consumedHolds as $hold) {
            $status = $this->getWeightedRandomStatus($orderStatuses);

            $createdAt = $hold->consumed_at ?? now()->subMinutes(rand(5, 120));

            $orderData = [
                'id' => (string) Str::uuid(),
                'product_id' => $hold->product_id,
                'stock_hold_id' => $hold->id,
                'quantity' => $hold->quantity,
                'unit_price' => $hold->product->price,
                'total_amount' => $hold->quantity * $hold->product->price,
                'status' => $status,
                'customer_email' => $this->generateCustomerEmail(),
                'customer_details' => $this->generateCustomerDetails(),
                'created_at' => $createdAt,
                'updated_at' => now(),
            ];

            if ($status === 'paid') {
                $orderData['paid_at'] = (clone $createdAt)->addMinutes(rand(1, 10));
                $revenue += $orderData['total_amount'];
            } elseif ($status === 'cancelled' || $status === 'failed') {
                $orderData['cancelled_at'] = (clone $createdAt)->addMinutes(rand(2, 15));
            }

            // Optional idempotency keys
            if (rand(0, 1)) {
                $orderData['webhook_idempotency_key'] = 'wh_' . Str::random(20);
                $orderData['payment_idempotency_key'] = 'pay_' . Str::random(20);
            }

            Order::create($orderData);
            $createdCount++;
        }

        // Create sample/edge case orders safely
        $this->createSampleOrders();
        $this->createEdgeCaseOrders();

        $this->command->info("💰 Created {$createdCount} orders with total revenue: $" . number_format($revenue, 2));
        $this->command->info('📈 Order status distribution simulated');
    }

    private function getWeightedRandomStatus(array $statuses): string
    {
        $total = array_sum($statuses);
        $random = rand(1, $total);
        $current = 0;

        foreach ($statuses as $status => $weight) {
            $current += $weight;
            if ($random <= $current) {
                return $status;
            }
        }

        return 'pending';
    }

    private function generateCustomerEmail(): string
    {
        $domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'example.com'];
        return Str::random(8) . '@' . $domains[array_rand($domains)];
    }

    private function generateCustomerDetails(): array
    {
        $firstNames = ['John', 'Jane', 'Mike', 'Sarah', 'David', 'Lisa', 'Chris', 'Emily'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];

        return [
            'name' => $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)],
            'phone' => '+1-' . rand(200, 999) . '-' . rand(200, 999) . '-' . rand(1000, 9999),
            'billing_address' => [
                'street' => rand(100, 9999) . ' ' . ['Main St', 'Oak Ave', 'Pine Rd', 'Maple Dr'][array_rand([0,1,2,3])],
                'city' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix'][array_rand([0,1,2,3,4])],
                'state' => ['CA', 'NY', 'TX', 'FL', 'IL'][array_rand([0,1,2,3,4])],
                'zip_code' => rand(10000, 99999),
                'country' => 'US'
            ]
        ];
    }

    private function createSampleOrders(): void
    {
        $products = Product::active()->get();

        for ($i = 0; $i < 10; $i++) {
            $product = $products->random();
            $quantity = rand(1, 2);

            // Only create order if product has at least one hold
            $hold = StockHold::where('product_id', $product->id)
                ->where('status', 'pending')
                ->inRandomOrder()
                ->first();

            $holdId = $hold?->id; // null if none

            Order::create([
                'id' => (string) Str::uuid(),
                'product_id' => $product->id,
                'stock_hold_id' => $holdId,
                'quantity' => $quantity,
                'unit_price' => $product->price,
                'total_amount' => $quantity * $product->price,
                'status' => ['paid', 'pending', 'failed'][array_rand([0,1,2])],
                'customer_email' => $this->generateCustomerEmail(),
                'customer_details' => $this->generateCustomerDetails(),
                'created_at' => now()->subMinutes(rand(1, 240)),
                'updated_at' => now(),
            ]);
        }
    }

    private function createEdgeCaseOrders(): void
    {
        $this->command->info('   Creating edge case orders...');
        $products = Product::active()->get();
        $product = $products->first();

        // High quantity order
        $hold = StockHold::where('product_id', $product->id)->inRandomOrder()->first();
        Order::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'stock_hold_id' => $hold?->id,
            'quantity' => 10,
            'unit_price' => $product->price,
            'total_amount' => 10 * $product->price,
            'status' => 'pending',
            'customer_email' => 'bulk_buyer@example.com',
            'customer_details' => ['name' => 'Bulk Buyer'],
            'created_at' => now()->subMinutes(5),
            'updated_at' => now(),
        ]);

        // Very old pending order
        $hold = StockHold::where('product_id', $product->id)->inRandomOrder()->first();
        Order::create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'stock_hold_id' => $hold?->id,
            'quantity' => 1,
            'unit_price' => $product->price,
            'total_amount' => $product->price,
            'status' => 'pending',
            'customer_email' => 'old_order@example.com',
            'customer_details' => ['name' => 'Old Customer'],
            'created_at' => now()->subHours(48),
            'updated_at' => now(),
        ]);

        $this->command->info('     ✅ Created edge case orders for testing');
    }
}














// namespace Database\Seeders;

// use App\Models\Order;
// use App\Models\Product;
// use App\Models\StockHold;
// use Illuminate\Database\Seeder;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Str;

// class OrderSeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
//     public function run(): void
//     {
//         $this->command->info('🛒 Seeding orders...');

//         // Get consumed holds to create orders from
//         $consumedHolds = StockHold::where('status', 'consumed')->get();

//         if ($consumedHolds->isEmpty()) {
//             $this->command->warn('⚠️  No consumed holds found. Creating sample orders...');
//             $this->createSampleOrders();
//             return;
//         }

//         $orderStatuses = [
//             'paid' => 60, // 60% of orders
//             'pending' => 25, // 25% of orders
//             'failed' => 10, // 10% of orders
//             'cancelled' => 5, // 5% of orders
//         ];

//         $createdCount = 0;
//         $revenue = 0;

//         foreach ($consumedHolds as $hold) {
//             $status = $this->getWeightedRandomStatus($orderStatuses);
            
//             $orderData = [
//                 'id' => (string) Str::uuid(),
//                 'product_id' => $hold->product_id,
//                 'stock_hold_id' => $hold->id,
//                 'quantity' => $hold->quantity,
//                 'unit_price' => $hold->product->price,
//                 'total_amount' => $hold->quantity * $hold->product->price,
//                 'status' => $status,
//                 'customer_email' => $this->generateCustomerEmail(),
//                 'customer_details' => $this->generateCustomerDetails(),
//                 'created_at' => $hold->consumed_at ?? now()->subMinutes(rand(5, 120)),
//                 'updated_at' => now(),
//             ];

//             if ($status === 'paid') {
//                 $orderData['paid_at'] = $orderData['created_at']->addMinutes(rand(1, 10));
//                 $revenue += $orderData['total_amount'];
//             } elseif ($status === 'cancelled' || $status === 'failed') {
//                 $orderData['cancelled_at'] = $orderData['created_at']->addMinutes(rand(2, 15));
//             }

//             // Add idempotency keys for some orders
//             if (rand(0, 1)) {
//                 $orderData['webhook_idempotency_key'] = 'wh_' . Str::random(20);
//                 $orderData['payment_idempotency_key'] = 'pay_' . Str::random(20);
//             }

//             Order::create($orderData);
//             $createdCount++;

//             // Update product metrics
//             if ($status === 'paid') {
//                 // Product sold count would be updated here
//             }
//         }

//         // Create some orders without holds (edge cases)
//         $this->createEdgeCaseOrders();

//         $this->command->info("💰 Created {$createdCount} orders with total revenue: $" . number_format($revenue, 2));
//         $this->command->info('📈 Order status distribution simulated');
//     }

//     private function getWeightedRandomStatus(array $statuses): string
//     {
//         $total = array_sum($statuses);
//         $random = rand(1, $total);
//         $current = 0;

//         foreach ($statuses as $status => $weight) {
//             $current += $weight;
//             if ($random <= $current) {
//                 return $status;
//             }
//         }

//         return 'pending';
//     }

//     private function generateCustomerEmail(): string
//     {
//         $domains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'example.com'];
//         $username = Str::random(8);
//         $domain = $domains[array_rand($domains)];
        
//         return "{$username}@{$domain}";
//     }

//     private function generateCustomerDetails(): array
//     {
//         $firstNames = ['John', 'Jane', 'Mike', 'Sarah', 'David', 'Lisa', 'Chris', 'Emily'];
//         $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];
        
//         return [
//             'name' => $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)],
//             'phone' => '+1-' . rand(200, 999) . '-' . rand(200, 999) . '-' . rand(1000, 9999),
//             'billing_address' => [
//                 'street' => rand(100, 9999) . ' ' . ['Main St', 'Oak Ave', 'Pine Rd', 'Maple Dr'][array_rand([0,1,2,3])],
//                 'city' => ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix'][array_rand([0,1,2,3,4])],
//                 'state' => ['CA', 'NY', 'TX', 'FL', 'IL'][array_rand([0,1,2,3,4])],
//                 'zip_code' => rand(10000, 99999),
//                 'country' => 'US'
//             ]
//         ];
//     }

//     private function createSampleOrders(): void
//     {
//         $products = Product::active()->get();

//         for ($i = 0; $i < 15; $i++) {
//             $product = $products->random();
//             $quantity = rand(1, 2);
            
//             Order::create([
//                 'id' => (string) Str::uuid(),
//                 'product_id' => $product->id,
//                 'stock_hold_id' => null, // No hold for sample orders
//                 'quantity' => $quantity,
//                 'unit_price' => $product->price,
//                 'total_amount' => $quantity * $product->price,
//                 'status' => ['paid', 'pending', 'failed'][array_rand([0,1,2])],
//                 'customer_email' => $this->generateCustomerEmail(),
//                 'customer_details' => $this->generateCustomerDetails(),
//                 'created_at' => now()->subMinutes(rand(1, 240)),
//                 'updated_at' => now(),
//             ]);
//         }
//     }

//     private function createEdgeCaseOrders(): void
//     {
//         $this->command->info('   Creating edge case orders...');

//         $products = Product::active()->get();
        
//         // Order with very high quantity
//         $product = $products->first();
//         Order::create([
//             'id' => (string) Str::uuid(),
//             'product_id' => $product->id,
//             'stock_hold_id' => null,
//             'quantity' => 10,
//             'unit_price' => $product->price,
//             'total_amount' => 10 * $product->price,
//             'status' => 'pending',
//             'customer_email' => 'bulk_buyer@example.com',
//             'customer_details' => ['name' => 'Bulk Buyer'],
//             'created_at' => now()->subMinutes(5),
//         ]);

//         // Very old pending order
//         Order::create([
//             'id' => (string) Str::uuid(),
//             'product_id' => $product->id,
//             'stock_hold_id' => null,
//             'quantity' => 1,
//             'unit_price' => $product->price,
//             'total_amount' => $product->price,
//             'status' => 'pending',
//             'customer_email' => 'old_order@example.com',
//             'customer_details' => ['name' => 'Old Customer'],
//             'created_at' => now()->subHours(48), // Should be auto-cancelled
//         ]);

//         $this->command->info('     ✅ Created edge case orders for testing');
//     }
// }
