<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting database seeding...');

        // Clear all caches
        $this->clearCaches();

        // Disable foreign key checks for truncation
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        // Truncate tables in order respecting foreign keys
        $this->truncateTables();

        // Enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Seed tables in proper order
        $this->call([
            ProductSeeder::class,
            StockHoldSeeder::class,
            OrderSeeder::class,
            IdempotencyKeySeeder::class,
            // PaymentWebhookSeeder::class,
            // FlashSaleScenarioSeeder::class,
        ]);

        // Generate system performance metrics
        $this->generatePerformanceMetrics();

        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('🎯 Flash sale system ready for testing.');
        $this->command->info('📊 Use the /api/metrics endpoint to view system statistics.');
    }

    private function clearCaches(): void
    {
        Cache::flush();
        $this->command->info('🧹 All caches cleared...');
    }

    private function truncateTables(): void
    {
        $tables = [
            'failed_webhooks',
            'payment_webhooks',
            'orders',
            'stock_holds',
            'idempotency_keys',
            'products',
            'product_stock_cache',
            'failed_jobs',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
            $this->command->info("🗑️  Truncated table: {$table}");
        }
    }

    private function generatePerformanceMetrics(): void
    {
        $metrics = [
            'system_start_time' => now()->toISOString(),
            'seed_version' => '1.0.0',
            'test_scenarios' => [
                'concurrent_holds' => 50,
                'order_conversion' => 35,
                'webhook_processing' => 25,
            ],
            'performance_targets' => [
                'max_response_time_ms' => 200,
                'concurrent_users' => 1000,
                'throughput_rps' => 100,
            ]
        ];

        Cache::put('system:metrics:seed', $metrics, 3600);
        $this->command->info('📊 Performance metrics generated.');
    }
}













// namespace Database\Seeders;

// use Illuminate\Database\Seeder;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Hash;
// use Illuminate\Support\Str;
// use Illuminate\Support\Facades\Cache;

// class DatabaseSeeder extends Seeder
// {
//     /**
//      * Seed the application's database.
//      */
//     public function run(): void
//     {
//         // Clear all caches before seeding
//         $this->clearCaches();

//         // Disable foreign key checks for faster seeding
//         DB::statement('SET FOREIGN_KEY_CHECKS=0');

//         // Truncate tables in correct order to maintain foreign key constraints
//         $this->truncateTables();

//         // Enable foreign key checks
//         DB::statement('SET FOREIGN_KEY_CHECKS=1');

//         // Seed the database
//         $this->call([
//             ProductSeeder::class,
//             StockHoldSeeder::class,
//             OrderSeeder::class,
//             PaymentWebhookSeeder::class,
//             IdempotencyKeySeeder::class,
//             FlashSaleScenarioSeeder::class,
//         ]);

//         // Generate performance metrics
//         $this->generatePerformanceMetrics();

//         $this->command->info('✅ Database seeded successfully!');
//         $this->command->info('🎯 Flash sale system ready for testing.');
//         $this->command->info('📊 Use the /api/metrics endpoint to view system statistics.');
//     }

//     private function clearCaches(): void
//     {
//         Cache::flush();
//         $this->command->info('All caches cleared...');
//     }

//     private function truncateTables(): void
//     {
//         $tables = [
//             'failed_webhooks',
//             'payment_webhooks',
//             'orders',
//             'stock_holds',
//             'idempotency_keys',
//             'products',
//             'product_stock_cache',
//             'failed_jobs',
//         ];

//         foreach ($tables as $table) {
//             DB::table($table)->truncate();
//             $this->command->info("🗑️  Truncated table: {$table}");
//         }
//     }

//     private function generatePerformanceMetrics(): void
//     {
//         // Generate some performance metrics for realistic testing
//         $metrics = [
//             'system_start_time' => now()->toISOString(),
//             'seed_version' => '1.0.0',
//             'test_scenarios' => [
//                 'concurrent_holds' => 50,
//                 'order_conversion' => 35,
//                 'webhook_processing' => 25,
//             ],
//             'performance_targets' => [
//                 'max_response_time_ms' => 200,
//                 'concurrent_users' => 1000,
//                 'throughput_rps' => 100,
//             ]
//         ];

//         Cache::put('system:metrics:seed', $metrics, 3600);
//     }
// }
