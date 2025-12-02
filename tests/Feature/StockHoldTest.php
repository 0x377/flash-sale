<?php

namespace Tests\Feature;

use Tests\Feature\FlashSaleTestCase;
use App\Models\StockHold;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class StockHoldTest extends FlashSaleTestCase
{
    public function test_can_create_stock_hold(): void
    {
        $response = $this->postJson('/api/holds', [
            'product_id' => $this->flashSaleProduct->id,
            'quantity' => 2,
            'session_id' => 'test_session_123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'hold_id',
                    'product_id',
                    'quantity',
                    'expires_at',
                    'expires_in_seconds'
                ],
                'meta'
            ]);

        $this->assertDatabaseHas('stock_holds', [
            'product_id' => $this->flashSaleProduct->id,
            'quantity' => 2,
            'status' => 'pending',
        ]);
    }

    public function test_hold_creation_reduces_available_stock(): void
    {
        $initialStock = $this->flashSaleProduct->available_stock;

        $this->postJson('/api/holds', [
            'product_id' => $this->flashSaleProduct->id,
            'quantity' => 3,
        ]);

        $this->assertEquals(
            $initialStock - 3,
            $this->flashSaleProduct->fresh()->available_stock
        );
    }

    public function test_cannot_create_hold_with_insufficient_stock(): void
    {
        $response = $this->postJson('/api/holds', [
            'product_id' => $this->flashSaleProduct->id,
            'quantity' => $this->initialStock + 10,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error', 'message']);
    }

    public function test_cannot_create_hold_for_inactive_product(): void
    {
        $inactiveProduct = \App\Models\Product::factory()->create(['is_active' => false]);

        $response = $this->postJson('/api/holds', [
            'product_id' => $inactiveProduct->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
    }

    public function test_hold_validation_rules(): void
    {
        $response = $this->postJson('/api/holds', [
            'product_id' => 'invalid',
            'quantity' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'quantity']);
    }

    public function test_can_retrieve_hold_status(): void
    {
        $hold = $this->createStockHold();

        $response = $this->getJson("/api/holds/{$hold->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'product_id',
                    'quantity',
                    'status',
                    'expires_at',
                    'is_active'
                ]
            ]);
    }

    public function test_can_manually_release_hold(): void
    {
        $hold = $this->createStockHold(2);
        $initialStock = $this->flashSaleProduct->available_stock;

        $response = $this->deleteJson("/api/holds/{$hold->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.released', true);

        // Stock should be returned
        $this->assertEquals(
            $initialStock,
            $this->flashSaleProduct->fresh()->available_stock
        );

        $this->assertDatabaseHas('stock_holds', [
            'id' => $hold->id,
            'status' => 'expired',
        ]);
    }

    public function test_hold_idempotency(): void
    {
        $idempotencyKey = $this->generateIdempotencyKey();

        $requestData = [
            'product_id' => $this->flashSaleProduct->id,
            'quantity' => 1,
        ];

        // First request
        $response1 = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/holds', $requestData);

        $response1->assertStatus(201);
        $holdId1 = $response1->json('data.hold_id');

        // Second request with same idempotency key
        $response2 = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/holds', $requestData);

        $response2->assertStatus(200); // Should return cached response
        $holdId2 = $response2->json('data.hold_id');

        $this->assertEquals($holdId1, $holdId2);
        $this->assertDatabaseCount('stock_holds', 1); // Only one hold should be created
    }
}
