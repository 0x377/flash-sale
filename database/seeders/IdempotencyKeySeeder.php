<?php

namespace Database\Seeders;

use App\Models\IdempotencyKey;
use App\Models\Order;
use App\Models\PaymentWebhook;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IdempotencyKeySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🔑 Seeding idempotency keys...');

        // Define resource types and counts
        $resourceTypes = [
            'stock_hold' => 10,
            'order_creation' => 8,
            'payment_webhook' => 12,
            'product_update' => 3,
        ];

        $createdCount = 0;

        foreach ($resourceTypes as $resourceType => $count) {
            $this->command->info("   Creating {$resourceType} idempotency keys...");

            for ($i = 0; $i < $count; $i++) {
                $keyData = [
                    'id' => (string) Str::uuid(),
                    'key' => $this->generateUniqueKey($resourceType),
                    'resource_type' => $resourceType,
                    'request_params' => $this->generateRequestParams($resourceType),
                    'created_at' => now()->subMinutes(rand(1, 120)),
                    'updated_at' => now(),
                ];

                // Optional linked resource
                if (in_array($resourceType, ['order_creation', 'payment_webhook']) && rand(0, 1)) {
                    $keyData['resource_id'] = $this->getRandomResourceId($resourceType);
                }

                // Randomly mark some keys as completed
                if (rand(0, 1)) {
                    $keyData['response'] = $this->generateResponse($resourceType);
                    $keyData['response_code'] = 200;
                    $keyData['completed_at'] = $keyData['created_at']->copy()->addSeconds(rand(1, 5));
                } else {
                    // Some keys are still locked
                    if (rand(0, 1)) {
                        $keyData['locked_at'] = now()->subMinutes(rand(1, 5));
                    }
                }

                IdempotencyKey::create($keyData);
                $createdCount++;
            }
        }

        // Create safe duplicate keys for testing (use unique suffixes per resource type)
        $this->createSafeDuplicateKeys();

        $this->command->info("🎯 Created {$createdCount} idempotency keys");
        $this->command->info('🔄 Duplicate key scenarios created safely for testing');
    }

    private function generateUniqueKey(string $resourceType): string
    {
        return match($resourceType) {
            'stock_hold' => 'hold_' . Str::uuid(),
            'order_creation' => 'order_' . Str::uuid(),
            'payment_webhook' => 'webhook_' . Str::uuid(),
            'product_update' => 'product_' . Str::uuid(),
            default => 'gen_' . Str::uuid()
        };
    }

    private function generateRequestParams(string $resourceType): array
    {
        return match($resourceType) {
            'stock_hold' => [
                'product_id' => rand(1, 5),
                'quantity' => rand(1, 3),
                'session_id' => 'session_' . Str::random(8),
            ],
            'order_creation' => [
                'hold_id' => (string) Str::uuid(),
                'customer_email' => 'test@example.com',
                'customer_details' => ['name' => 'Test Customer'],
            ],
            'payment_webhook' => [
                'order_id' => (string) Str::uuid(),
                'payment_status' => 'success',
                'amount' => rand(1000, 5000) / 100,
            ],
            'product_update' => [
                'price' => rand(1000, 5000) / 100,
                'stock' => rand(10, 100),
            ],
            default => []
        };
    }

    private function getRandomResourceId(string $resourceType): ?string
    {
        return match($resourceType) {
            'order_creation' => Order::inRandomOrder()->value('id'),
            'payment_webhook' => PaymentWebhook::inRandomOrder()->value('id'),
            default => null
        };
    }

    private function generateResponse(string $resourceType): array
    {
        return match($resourceType) {
            'stock_hold' => [
                'hold_id' => (string) Str::uuid(),
                'expires_at' => now()->addMinutes(2)->toISOString(),
                'success' => true,
            ],
            'order_creation' => [
                'order_id' => (string) Str::uuid(),
                'status' => 'pending',
                'amount' => rand(1000, 5000) / 100,
            ],
            'payment_webhook' => [
                'processed' => true,
                'order_status' => 'paid',
                'webhook_id' => (string) Str::uuid(),
            ],
            default => ['success' => true]
        };
    }

    private function createSafeDuplicateKeys(): void
    {
        $this->command->info('   Creating safe duplicate idempotency keys...');

        $resourceTypes = ['stock_hold', 'order_creation', 'payment_webhook'];

        foreach ($resourceTypes as $resourceType) {
            $baseKey = $resourceType . '_duplicate_' . Str::random(8);
            for ($i = 0; $i < 2; $i++) {
                IdempotencyKey::create([
                    'id' => (string) Str::uuid(),
                    'key' => $baseKey . "_{$i}", // ensure uniqueness
                    'resource_type' => $resourceType,
                    'request_params' => $this->generateRequestParams($resourceType),
                    'response' => $this->generateResponse($resourceType),
                    'response_code' => 200,
                    'completed_at' => now()->subMinutes(rand(1, 30)),
                    'created_at' => now()->subMinutes(rand(31, 60)),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}











// namespace Database\Seeders;

// use App\Models\IdempotencyKey;
// use App\Models\Order;
// use App\Models\PaymentWebhook;
// use Illuminate\Database\Seeder;
// use Illuminate\Support\Str;

// class IdempotencyKeySeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      */
//     public function run(): void
//     {
//         $this->command->info('🔑 Seeding idempotency keys...');

//         // Create idempotency keys for various resource types
//         $resourceTypes = [
//             'stock_hold' => 10,
//             'order_creation' => 8,
//             'payment_webhook' => 12,
//             'product_update' => 3,
//         ];

//         $createdCount = 0;

//         foreach ($resourceTypes as $resourceType => $count) {
//             $this->command->info("   Creating {$resourceType} idempotency keys...");

//             for ($i = 0; $i < $count; $i++) {
//                 $keyData = [
//                     'id' => (string) Str::uuid(),
//                     'key' => $this->generateIdempotencyKey($resourceType),
//                     'resource_type' => $resourceType,
//                     'request_params' => $this->generateRequestParams($resourceType),
//                     'created_at' => now()->subMinutes(rand(1, 120)),
//                     'updated_at' => now(),
//                 ];

//                 // Add resource_id for some keys
//                 if (in_array($resourceType, ['order_creation', 'payment_webhook']) && rand(0, 1)) {
//                     $keyData['resource_id'] = $this->getRandomResourceId($resourceType);
//                 }

//                 // Mark some keys as completed
//                 if (rand(0, 1)) {
//                     $keyData['response'] = $this->generateResponse($resourceType);
//                     $keyData['response_code'] = 200;
//                     $keyData['completed_at'] = $keyData['created_at']->addSeconds(rand(1, 5));
//                 } else {
//                     // Some keys are still locked or pending
//                     if (rand(0, 1)) {
//                         $keyData['locked_at'] = now()->subMinutes(rand(1, 5));
//                     }
//                 }

//                 IdempotencyKey::create($keyData);
//                 $createdCount++;
//             }
//         }

//         // Create duplicate idempotency keys for testing
//         $this->createDuplicateIdempotencyKeys();

//         $this->command->info("🎯 Created {$createdCount} idempotency keys for various operations");
//         $this->command->info('🔄 Created duplicate key scenarios for idempotency testing');
//     }

//     private function generateIdempotencyKey(string $resourceType): string
//     {
//         $prefix = match($resourceType) {
//             'stock_hold' => 'hold_',
//             'order_creation' => 'order_',
//             'payment_webhook' => 'webhook_',
//             'product_update' => 'product_',
//             default => 'gen_'
//         };

//         return $prefix . Str::random(16) . '_' . time();
//     }

//     private function generateRequestParams(string $resourceType): array
//     {
//         return match($resourceType) {
//             'stock_hold' => [
//                 'product_id' => rand(1, 5),
//                 'quantity' => rand(1, 3),
//                 'session_id' => 'session_' . Str::random(8),
//             ],
//             'order_creation' => [
//                 'hold_id' => (string) Str::uuid(),
//                 'customer_email' => 'test@example.com',
//                 'customer_details' => ['name' => 'Test Customer'],
//             ],
//             'payment_webhook' => [
//                 'order_id' => (string) Str::uuid(),
//                 'payment_status' => 'success',
//                 'amount' => rand(1000, 5000) / 100,
//             ],
//             'product_update' => [
//                 'price' => rand(1000, 5000) / 100,
//                 'stock' => rand(10, 100),
//             ],
//             default => []
//         };
//     }

//     private function getRandomResourceId(string $resourceType): ?string
//     {
//         return match($resourceType) {
//             'order_creation' => Order::inRandomOrder()->value('id'),
//             'payment_webhook' => PaymentWebhook::inRandomOrder()->value('id'),
//             default => null
//         };
//     }

//     private function generateResponse(string $resourceType): array
//     {
//         return match($resourceType) {
//             'stock_hold' => [
//                 'hold_id' => (string) Str::uuid(),
//                 'expires_at' => now()->addMinutes(2)->toISOString(),
//                 'success' => true,
//             ],
//             'order_creation' => [
//                 'order_id' => (string) Str::uuid(),
//                 'status' => 'pending',
//                 'amount' => rand(1000, 5000) / 100,
//             ],
//             'payment_webhook' => [
//                 'processed' => true,
//                 'order_status' => 'paid',
//                 'webhook_id' => (string) Str::uuid(),
//             ],
//             default => ['success' => true]
//         };
//     }

//     private function createDuplicateIdempotencyKeys(): void
//     {
//         $this->command->info('   Creating duplicate idempotency keys...');

//         $duplicateKey = 'duplicate_' . Str::random(16);
        
//         // Create multiple records with the same key but different resource types
//         $resourceTypes = ['stock_hold', 'order_creation', 'payment_webhook'];
        
//         foreach ($resourceTypes as $resourceType) {
//             IdempotencyKey::create([
//                 'id' => (string) Str::uuid(),
//                 'key' => $duplicateKey,
//                 'resource_type' => $resourceType,
//                 'request_params' => $this->generateRequestParams($resourceType),
//                 'response' => $this->generateResponse($resourceType),
//                 'response_code' => 200,
//                 'completed_at' => now()->subMinutes(rand(1, 30)),
//                 'created_at' => now()->subMinutes(rand(31, 60)),
//                 'updated_at' => now(),
//             ]);
//         }

//         // Create multiple completed records with same key and resource type (true duplicates)
//         for ($i = 0; $i < 3; $i++) {
//             IdempotencyKey::create([
//                 'id' => (string) Str::uuid(),
//                 'key' => 'true_duplicate_' . Str::random(16),
//                 'resource_type' => 'payment_webhook',
//                 'request_params' => $this->generateRequestParams('payment_webhook'),
//                 'response' => $this->generateResponse('payment_webhook'),
//                 'response_code' => 200,
//                 'completed_at' => now()->subMinutes(rand(1, 10)),
//                 'created_at' => now()->subMinutes(rand(11, 20)),
//                 'updated_at' => now(),
//             ]);
//         }
//     }
// }
