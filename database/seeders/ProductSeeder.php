<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎁 Seeding products...');

        $products = [
            [
                'name' => 'Flash Sale Smartphone X1',
                'description' => 'High-performance smartphone with advanced camera system and long-lasting battery.',
                'price' => 499.99,
                'initial_stock' => 100,
                'available_stock' => 100,
                'is_active' => true,
                'metadata' => [
                    'sku' => 'FS-SPX1-2024',
                    'category' => 'electronics',
                    'brand' => 'TechCorp',
                    'specs' => [
                        'display' => '6.1-inch OLED',
                        'storage' => '128GB',
                        'camera' => '48MP Triple Camera',
                        'battery' => '4000mAh'
                    ],
                    'tags' => ['flash-sale', 'limited-stock', 'premium'],
                    'weight_grams' => 189,
                    'dimensions' => '146.7 x 71.5 x 7.7 mm'
                ]
            ],
            [
                'name' => 'Gaming Laptop Pro',
                'description' => 'Powerful gaming laptop with RTX graphics and high-refresh display.',
                'price' => 1299.99,
                'initial_stock' => 25,
                'available_stock' => 25,
                'is_active' => true,
                'metadata' => [
                    'sku' => 'FS-GLP-2024',
                    'category' => 'computers',
                    'brand' => 'GameMaster',
                    'specs' => [
                        'processor' => 'Intel i7-13700H',
                        'graphics' => 'RTX 4060 8GB',
                        'ram' => '16GB DDR5',
                        'storage' => '1TB NVMe SSD',
                        'display' => '15.6" 165Hz IPS'
                    ],
                    'tags' => ['gaming', 'high-demand', 'limited-edition'],
                    'weight_grams' => 2300,
                    'dimensions' => '360 x 259 x 22.9 mm'
                ]
            ],
            [
                'name' => 'Wireless Noise-Canceling Headphones',
                'description' => 'Premium over-ear headphones with active noise cancellation and 30-hour battery.',
                'price' => 299.99,
                'initial_stock' => 50,
                'available_stock' => 50,
                'is_active' => true,
                'metadata' => [
                    'sku' => 'FS-WNCH-2024',
                    'category' => 'audio',
                    'brand' => 'SoundMax',
                    'specs' => [
                        'battery_life' => '30 hours',
                        'connectivity' => 'Bluetooth 5.3',
                        'noise_cancellation' => 'Active',
                        'driver_size' => '40mm'
                    ],
                    'tags' => ['audio', 'wireless', 'premium'],
                    'weight_grams' => 254,
                    'color_options' => ['black', 'silver', 'blue']
                ]
            ],
            [
                'name' => 'Smart Fitness Watch',
                'description' => 'Advanced fitness tracker with heart rate monitoring and GPS.',
                'price' => 199.99,
                'initial_stock' => 75,
                'available_stock' => 75,
                'is_active' => false, // Inactive for testing
                'metadata' => [
                    'sku' => 'FS-SFW-2024',
                    'category' => 'wearables',
                    'brand' => 'FitTech',
                    'specs' => [
                        'display' => '1.4-inch AMOLED',
                        'battery_life' => '7 days',
                        'water_resistance' => '5ATM',
                        'sensors' => ['Heart Rate', 'GPS', 'SpO2']
                    ],
                    'tags' => ['fitness', 'smartwatch', 'health'],
                    'weight_grams' => 38
                ]
            ],
            [
                'name' => '4K Ultra HD Smart TV',
                'description' => '55-inch 4K Smart TV with HDR and streaming apps.',
                'price' => 699.99,
                'initial_stock' => 10,
                'available_stock' => 10,
                'is_active' => true,
                'metadata' => [
                    'sku' => 'FS-4KTV-2024',
                    'category' => 'televisions',
                    'brand' => 'VisionPlus',
                    'specs' => [
                        'screen_size' => '55-inch',
                        'resolution' => '3840x2160 4K',
                        'hdr' => 'HDR10+',
                        'smart_platform' => 'WebOS',
                        'refresh_rate' => '60Hz'
                    ],
                    'tags' => ['tv', '4k', 'smart', 'limited-stock'],
                    'weight_grams' => 14200,
                    'dimensions' => '122.8 x 71.7 x 8.9 cm'
                ]
            ]
        ];

        $createdCount = 0;
        $totalStock = 0;

        foreach ($products as $productData) {
            $product = Product::create($productData);
            $createdCount++;
            $totalStock += $product->available_stock;

            $this->command->info("   ✅ Created: {$product->name} (Stock: {$product->available_stock})");
        }

        // Cache the main flash sale product
        $flashSaleProduct = Product::where('is_active', true)->first();
        if ($flashSaleProduct) {
            Cache::put('flash_sale:active_product', $flashSaleProduct->id, 3600);
        }

        $this->command->info("📦 Created {$createdCount} products with total stock: {$totalStock}");
        $this->command->info('🎯 Main flash sale product: ' . ($flashSaleProduct->name ?? 'None'));

        // Generate product performance baselines
        $this->generateProductPerformanceData();
    }

    private function generateProductPerformanceData(): void
    {
        $products = Product::all();

        foreach ($products as $product) {
            $performanceData = [
                'views_today' => rand(100, 5000),
                'conversion_rate' => rand(5, 25) / 100, // 5-25%
                'average_hold_time_minutes' => rand(1, 5),
                'abandonment_rate' => rand(10, 40) / 100, // 10-40%
                'peak_concurrent_holds' => rand(5, 50),
                'last_peak_time' => now()->subMinutes(rand(10, 120))->toISOString()
            ];

            Cache::put("product:{$product->id}:performance", $performanceData, 86400);
        }

        $this->command->info('📊 Generated product performance baselines');
    }
}
