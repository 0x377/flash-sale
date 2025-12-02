<?php

namespace Tests\Feature;

use Tests\Feature\FlashSaleTestCase;
use Illuminate\Support\Facades\Cache;

class ProductEndpointTest extends FlashSaleTestCase
{
    public function test_can_retrieve_product_with_accurate_stock(): void
    {
        $response = $this->getJson("/api/products/{$this->flashSaleProduct->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'price',
                    'available_stock',
                    'initial_stock',
                    'is_active',
                ],
                'meta'
            ])
            ->assertJsonPath('data.available_stock', $this->initialStock);
    }

    public function test_product_endpoint_uses_caching(): void
    {
        // First request should cache the response
        $response1 = $this->getJson("/api/products/{$this->flashSaleProduct->id}");
        $response1->assertStatus(200);

        // Second request should use cache
        $response2 = $this->getJson("/api/products/{$this->flashSaleProduct->id}");
        $response2->assertStatus(200);

        // Verify cache was used by checking response headers or timing
        $this->assertTrue(Cache::has("product:{$this->flashSaleProduct->id}:details"));
    }

    public function test_product_stock_endpoint_returns_correct_value(): void
    {
        $response = $this->getJson("/api/products/{$this->flashSaleProduct->id}/stock");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'product_id',
                    'available_stock',
                    'timestamp'
                ]
            ])
            ->assertJsonPath('data.available_stock', $this->initialStock);
    }

    public function test_returns_404_for_nonexistent_product(): void
    {
        $response = $this->getJson('/api/products/99999');

        $response->assertStatus(404)
            ->assertJsonStructure(['error', 'message']);
    }

    public function test_returns_404_for_inactive_product(): void
    {
        $inactiveProduct = \App\Models\Product::factory()->create(['is_active' => false]);

        $response = $this->getJson("/api/products/{$inactiveProduct->id}");

        $response->assertStatus(404);
    }

    public function test_stock_updates_reflect_in_real_time(): void
    {
        // Create a hold to reduce available stock
        $hold = $this->createStockHold(5);

        $response = $this->getJson("/api/products/{$this->flashSaleProduct->id}/stock");

        $response->assertStatus(200)
            ->assertJsonPath('data.available_stock', $this->initialStock - 5);
    }
}
