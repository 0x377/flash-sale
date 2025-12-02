<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\PaymentWebhook;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentWebhookSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🌐 Seeding payment webhooks...');

        $orders = Order::all();

        if ($orders->isEmpty()) {
            $this->command->warn('⚠️  No orders found. Skipping payment webhook seeding.');
            return;
        }

        $webhookScenarios = [
            'successful' => ['count' => 8, 'status' => 'success'],
            'failed' => ['count' => 3, 'status' => 'failed'],
            'duplicate' => ['count' => 3, 'status' => 'success'], // intentional duplicates
            'retry' => ['count' => 2, 'status' => 'failed'],
        ];

        $createdCount = 0;
        $existingKeys = []; // track all unique keys
        $duplicateKeys = []; // track intentional duplicate keys

        foreach ($webhookScenarios as $scenario => $config) {
            $this->command->info("   Creating {$scenario} webhooks...");

            for ($i = 0; $i < $config['count']; $i++) {
                $order = $orders->random();

                // Generate a unique or controlled duplicate key
                $idempotencyKey = $this->generateWebhookKey($scenario, $existingKeys, $duplicateKeys);

                if ($scenario === 'duplicate') {
                    $duplicateKeys[] = $idempotencyKey; // save duplicate keys for reuse
                }

                $createdAt = $order->created_at->copy()->addMinutes(rand(1, 5));

                $webhookData = [
                    'id' => (string) Str::uuid(),
                    'idempotency_key' => $idempotencyKey,
                    'order_id' => $order->id,
                    'payment_provider' => $this->getRandomProvider(),
                    'payment_reference' => 'pay_' . Str::random(16),
                    'status' => $config['status'],
                    'amount' => $order->total_amount,
                    'currency' => 'USD',
                    'payload' => $this->generatePayload($order, $config['status']),
                    'attempts' => $scenario === 'retry' ? rand(2, 3) : 1,
                    'created_at' => $createdAt,
                    'updated_at' => now(),
                ];

                // Handle processed or retry fields
                if ($scenario !== 'retry') {
                    $webhookData['processed_at'] = $createdAt->copy()->addSeconds(rand(1, 10));
                } else {
                    $webhookData['next_retry_at'] = now()->addMinutes(rand(5, 15));
                }

                PaymentWebhook::create($webhookData);
                $createdCount++;
            }
        }

        // Create out-of-order webhooks
        $this->createOutOfOrderWebhooks($orders, $existingKeys);

        $this->command->info("📨 Created {$createdCount} payment webhooks successfully");
    }

    /**
     * Generate a unique or controlled duplicate idempotency key
     */
    private function generateWebhookKey(string $scenario, array &$existingKeys, array &$duplicateKeys): string
    {
        if ($scenario === 'duplicate' && !empty($duplicateKeys)) {
            // reuse one of the existing duplicate keys
            return $duplicateKeys[array_rand($duplicateKeys)];
        }

        // Generate a truly unique key
        do {
            $key = 'idemp_' . Str::random(20) . '_' . time();
        } while (in_array($key, $existingKeys));

        $existingKeys[] = $key;

        return $key;
    }

    /**
     * Get a random payment provider
     */
    private function getRandomProvider(): string
    {
        $providers = ['stripe', 'paypal', 'braintree', 'square', 'adyen'];
        return $providers[array_rand($providers)];
    }

    /**
     * Generate webhook payload based on order and status
     */
    private function generatePayload($order, string $status): array
    {
        $payload = [
            'event_type' => 'payment.' . $status,
            'payment_intent_id' => 'pi_' . Str::random(14),
            'amount_captured' => $status === 'success' ? $order->total_amount : 0,
            'currency' => 'usd',
            'customer' => $order->customer_email,
            'metadata' => [
                'order_id' => $order->id,
                'product_id' => $order->product_id,
                'quantity' => $order->quantity,
            ],
            'timestamp' => now()->toISOString(),
        ];

        if ($status === 'success') {
            $payload['payment_status'] = 'succeeded';
            $payload['charges'] = [
                'data' => [[
                    'id' => 'ch_' . Str::random(14),
                    'amount' => $order->total_amount * 100,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                    'paid' => true,
                ]]
            ];
        } else {
            $payload['payment_status'] = 'failed';
            $payload['error'] = [
                'type' => 'card_error',
                'code' => 'card_declined',
                'message' => 'Your card was declined.',
                'decline_code' => 'insufficient_funds',
            ];
        }

        return $payload;
    }

    /**
     * Create webhooks that arrive before order creation (out-of-order)
     */
    private function createOutOfOrderWebhooks($orders, array &$existingKeys): void
    {
        $this->command->info('   Creating out-of-order webhooks...');

        for ($i = 0; $i < 3; $i++) {
            $order = $orders->random();
            $createdAt = $order->created_at->copy()->subMinutes(rand(1, 3));

            // Generate unique key
            do {
                $key = 'ooo_' . Str::random(20);
            } while (in_array($key, $existingKeys));

            $existingKeys[] = $key;

            PaymentWebhook::create([
                'id' => (string) Str::uuid(),
                'idempotency_key' => $key,
                'order_id' => $order->id,
                'payment_provider' => 'stripe',
                'payment_reference' => 'pay_ooo_' . Str::random(14),
                'status' => 'success',
                'amount' => $order->total_amount,
                'currency' => 'USD',
                'payload' => $this->generatePayload($order, 'success'),
                'attempts' => 1,
                'processed_at' => $order->created_at->copy()->addSeconds(30),
                'created_at' => $createdAt,
                'updated_at' => $order->created_at->copy()->addSeconds(30),
            ]);
        }
    }
}












// namespace Database\Seeders;

// use App\Models\Order;
// use App\Models\PaymentWebhook;
// use Illuminate\Database\Seeder;
// use Illuminate\Support\Str;

// class PaymentWebhookSeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
//     public function run(): void
//     {
//         $this->command->info('🌐 Seeding payment webhooks...');

//         $orders = Order::all();

//         if ($orders->isEmpty()) {
//             $this->command->warn('⚠️  No orders found. Skipping payment webhook seeding.');
//             return;
//         }

//         $webhookScenarios = [
//             'successful' => ['count' => 8, 'status' => 'success'],
//             'failed' => ['count' => 3, 'status' => 'failed'],
//             'duplicate' => ['count' => 3, 'status' => 'success'],
//             'retry' => ['count' => 2, 'status' => 'failed'],
//         ];

//         $createdCount = 0;
//         $duplicateKeys = [];

//         foreach ($webhookScenarios as $scenario => $config) {
//             $this->command->info("   Creating {$scenario} webhooks...");

//             for ($i = 0; $i < $config['count']; $i++) {
//                 $order = $orders->random();

//                 // Generate unique or safe duplicate key
//                 $idempotencyKey = $this->generateWebhookKey($scenario, $duplicateKeys);

//                 if ($scenario === 'duplicate') {
//                     $duplicateKeys[] = $idempotencyKey; // Store for reuse
//                 }

//                 $createdAt = $order->created_at->copy()->addMinutes(rand(1, 5));

//                 $webhookData = [
//                     'id' => (string) Str::uuid(),
//                     'idempotency_key' => $idempotencyKey,
//                     'order_id' => $order->id,
//                     'payment_provider' => $this->getRandomProvider(),
//                     'payment_reference' => 'pay_' . Str::random(16),
//                     'status' => $config['status'],
//                     'amount' => $order->total_amount,
//                     'currency' => 'USD',
//                     'payload' => $this->generatePayload($order, $config['status']),
//                     'attempts' => $scenario === 'retry' ? rand(2, 3) : 1,
//                     'created_at' => $createdAt,
//                     'updated_at' => now(),
//                 ];

//                 // Processed or next retry
//                 if ($scenario !== 'retry') {
//                     $webhookData['processed_at'] = $createdAt->copy()->addSeconds(rand(1, 10));
//                 } else {
//                     $webhookData['next_retry_at'] = now()->addMinutes(rand(5, 15));
//                 }

//                 PaymentWebhook::create($webhookData);
//                 $createdCount++;
//             }
//         }

//         // Create out-of-order webhooks
//         $this->createOutOfOrderWebhooks($orders);

//         $this->command->info("📨 Created {$createdCount} payment webhooks successfully");
//     }

//     private function generateWebhookKey(string $scenario, array &$duplicateKeys): string
//     {
//         // Use an existing duplicate key for testing duplicates
//         if ($scenario === 'duplicate' && !empty($duplicateKeys)) {
//             return $duplicateKeys[array_rand($duplicateKeys)];
//         }

//         // Always generate a guaranteed unique key
//         $uniqueKey = (string) Str::uuid();

//         // Store it if this scenario will need it for duplicates later
//         if ($scenario === 'duplicate') {
//             $duplicateKeys[] = $uniqueKey;
//         }

//         return $uniqueKey;
//     }

//     private function getRandomProvider(): string
//     {
//         $providers = ['stripe', 'paypal', 'braintree', 'square', 'adyen'];
//         return $providers[array_rand($providers)];
//     }

//     private function generatePayload($order, string $status): array
//     {
//         $payload = [
//             'event_type' => 'payment.' . $status,
//             'payment_intent_id' => 'pi_' . Str::random(14),
//             'amount_captured' => $status === 'success' ? $order->total_amount : 0,
//             'currency' => 'usd',
//             'customer' => $order->customer_email,
//             'metadata' => [
//                 'order_id' => $order->id,
//                 'product_id' => $order->product_id,
//                 'quantity' => $order->quantity,
//             ],
//             'timestamp' => now()->toISOString(),
//         ];

//         if ($status === 'success') {
//             $payload['payment_status'] = 'succeeded';
//             $payload['charges'] = [
//                 'data' => [[
//                     'id' => 'ch_' . Str::random(14),
//                     'amount' => $order->total_amount * 100,
//                     'currency' => 'usd',
//                     'status' => 'succeeded',
//                     'paid' => true,
//                 ]]
//             ];
//         } else {
//             $payload['payment_status'] = 'failed';
//             $payload['error'] = [
//                 'type' => 'card_error',
//                 'code' => 'card_declined',
//                 'message' => 'Your card was declined.',
//                 'decline_code' => 'insufficient_funds',
//             ];
//         }

//         return $payload;
//     }

//     private function createOutOfOrderWebhooks($orders): void
//     {
//         $this->command->info('   Creating out-of-order webhooks...');

//         for ($i = 0; $i < 3; $i++) {
//             $order = $orders->random();
//             $createdAt = $order->created_at->copy()->subMinutes(rand(1, 3));

//             PaymentWebhook::create([
//                 'id' => (string) Str::uuid(),
//                 'idempotency_key' => 'ooo_' . Str::random(20),
//                 'order_id' => $order->id,
//                 'payment_provider' => 'stripe',
//                 'payment_reference' => 'pay_ooo_' . Str::random(14),
//                 'status' => 'success',
//                 'amount' => $order->total_amount,
//                 'currency' => 'USD',
//                 'payload' => $this->generatePayload($order, 'success'),
//                 'attempts' => 1,
//                 'processed_at' => $order->created_at->copy()->addSeconds(30),
//                 'created_at' => $createdAt,
//                 'updated_at' => $order->created_at->copy()->addSeconds(30),
//             ]);
//         }
//     }
// }









// namespace Database\Seeders;

// use App\Models\Order;
// use App\Models\PaymentWebhook;
// use Illuminate\Database\Seeder;
// use Illuminate\Support\Str;

// class PaymentWebhookSeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
//     public function run(): void
//     {
//         $this->command->info('🌐 Seeding payment webhooks...');

//         $orders = Order::all();

//         if ($orders->isEmpty()) {
//             $this->command->warn('⚠️  No orders found. Skipping payment webhook seeding.');
//             return;
//         }

//         $webhookTypes = [
//             'successful' => ['count' => 8, 'status' => 'success'],
//             'failed' => ['count' => 3, 'status' => 'failed'],
//             'duplicate' => ['count' => 4, 'status' => 'success'], // Same idempotency key
//             'retry' => ['count' => 2, 'status' => 'failed'], // Multiple attempts
//         ];

//         $createdCount = 0;
//         $duplicateKeys = [];

//         foreach ($webhookTypes as $type => $config) {
//             $this->command->info("   Creating {$type} webhooks...");

//             for ($i = 0; $i < $config['count']; $i++) {
//                 $order = $orders->random();
                
//                 $webhookData = [
//                     'id' => (string) Str::uuid(),
//                     'idempotency_key' => $this->generateIdempotencyKey($type, $duplicateKeys),
//                     'order_id' => $order->id,
//                     'payment_provider' => $this->getRandomPaymentProvider(),
//                     'payment_reference' => 'pay_' . Str::random(16),
//                     'status' => $config['status'],
//                     'amount' => $order->total_amount,
//                     'currency' => 'USD',
//                     'payload' => $this->generateWebhookPayload($order, $config['status']),
//                     'attempts' => $type === 'retry' ? rand(2, 3) : 1,
//                     'created_at' => $order->created_at->addMinutes(rand(1, 5)),
//                     'updated_at' => now(),
//                 ];

//                 if ($type !== 'retry') {
//                     $webhookData['processed_at'] = $webhookData['created_at']->addSeconds(rand(1, 10));
//                 } else {
//                     $webhookData['next_retry_at'] = now()->addMinutes(rand(5, 15));
//                 }

//                 if ($type === 'duplicate') {
//                     // Store the duplicate key for reuse
//                     $duplicateKeys[] = $webhookData['idempotency_key'];
//                 }

//                 PaymentWebhook::create($webhookData);
//                 $createdCount++;
//             }
//         }

//         // Create webhooks that arrived before order creation (out-of-order scenario)
//         $this->createOutOfOrderWebhooks($orders);

//         $this->command->info("📨 Created {$createdCount} payment webhooks with various scenarios");
//         $this->command->info('🔄 Created out-of-order webhook scenarios for testing');
//     }

//     private function generateIdempotencyKey(string $type, array &$duplicateKeys): string
//     {
//         if ($type === 'duplicate' && !empty($duplicateKeys)) {
//             return $duplicateKeys[array_rand($duplicateKeys)];
//         }

//         return 'idemp_' . Str::random(20) . '_' . time();
//     }

//     private function getRandomPaymentProvider(): string
//     {
//         $providers = ['stripe', 'paypal', 'braintree', 'square', 'adyen'];
//         return $providers[array_rand($providers)];
//     }

//     private function generateWebhookPayload(Order $order, string $status): array
//     {
//         $basePayload = [
//             'event_type' => 'payment.' . $status,
//             'payment_intent_id' => 'pi_' . Str::random(14),
//             'amount_captured' => $status === 'success' ? $order->total_amount : 0,
//             'currency' => 'usd',
//             'customer' => $order->customer_email,
//             'metadata' => [
//                 'order_id' => $order->id,
//                 'product_id' => $order->product_id,
//                 'quantity' => $order->quantity
//             ],
//             'timestamp' => now()->toISOString(),
//         ];

//         if ($status === 'success') {
//             $basePayload['payment_status'] = 'succeeded';
//             $basePayload['charges'] = [
//                 'data' => [[
//                     'id' => 'ch_' . Str::random(14),
//                     'amount' => $order->total_amount * 100, // in cents
//                     'currency' => 'usd',
//                     'status' => 'succeeded',
//                     'paid' => true,
//                 ]]
//             ];
//         } else {
//             $basePayload['payment_status'] = 'failed';
//             $basePayload['error'] = [
//                 'type' => 'card_error',
//                 'code' => 'card_declined',
//                 'message' => 'Your card was declined.',
//                 'decline_code' => 'insufficient_funds'
//             ];
//         }

//         return $basePayload;
//     }

//     private function createOutOfOrderWebhooks($orders): void
//     {
//         $this->command->info('   Creating out-of-order webhooks...');

//         for ($i = 0; $i < 3; $i++) {
//             $order = $orders->random();
            
//             // Create webhook that "arrived" before the order was created
//             $webhookTime = $order->created_at->subMinutes(rand(1, 3));

//             PaymentWebhook::create([
//                 'id' => (string) Str::uuid(),
//                 'idempotency_key' => 'ooo_' . Str::random(20),
//                 'order_id' => $order->id,
//                 'payment_provider' => 'stripe',
//                 'payment_reference' => 'pay_ooo_' . Str::random(14),
//                 'status' => 'success',
//                 'amount' => $order->total_amount,
//                 'currency' => 'USD',
//                 'payload' => $this->generateWebhookPayload($order, 'success'),
//                 'attempts' => 1,
//                 'processed_at' => $order->created_at->addSeconds(30), // Processed after order creation
//                 'created_at' => $webhookTime,
//                 'updated_at' => $order->created_at->addSeconds(30),
//             ]);
//         }
//     }
// }
