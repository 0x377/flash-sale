<?php

namespace Tests\Feature;

use Tests\Feature\FlashSaleTestCase;
use App\Models\StockHold;
use App\Models\Order;
use Illuminate\Support\Facades\Queue;

class OrderTest extends FlashSaleTestCase
{
    public function test_can_create_order_from_valid_hold(): void
    {
        $hold = $this->createStockHold(2);

        $response = $this->postJson('/api/orders', [
            'hold_id' => $hold->id,
            'customer_email' => 'test@example.com',
            'customer_details' => ['name' => 'Test Customer'],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'order_id',
                    'status',
                    'amount',
                    'product_id',
                    'quantity',
                    'created_at'
                ],
                'meta'
            ])
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('orders', [
            'stock_hold_id' => $hold->id,
            'status' => 'pending',
        ]);

        // Hold should be marked as consumed
        $this->assertEquals('consumed', $hold->fresh()->status);
    }

    public function test_cannot_create_order_from_expired_hold(): void
    {
        $hold = $this->createStockHold(1, [
            'expires_at' => now()->subMinutes(10),
        ]);

        $response = $this->postJson('/api/orders', [
            'hold_id' => $hold->id,
            'customer_email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error', 'message']);
    }

    public function test_cannot_create_order_from_already_used_hold(): void
    {
        $hold = $this->createStockHold(1);
        
        // Create first order
        $this->postJson('/api/orders', [
            'hold_id' => $hold->id,
            'customer_email' => 'test1@example.com',
        ])->assertStatus(201);

        // Try to create second order with same hold
        $response = $this->postJson('/api/orders', [
            'hold_id' => $hold->id,
            'customer_email' => 'test2@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_create_order_with_invalid_hold(): void
    {
        $response = $this->postJson('/api/orders', [
            'hold_id' => 'invalid-hold-id',
            'customer_email' => 'test@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['hold_id']);
    }

    public function test_order_idempotency(): void
    {
        $hold = $this->createStockHold(1);
        $idempotencyKey = $this->generateIdempotencyKey();

        $requestData = [
            'hold_id' => $hold->id,
            'customer_email' => 'test@example.com',
        ];

        // First request
        $response1 = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/orders', $requestData);

        $response1->assertStatus(201);
        $orderId1 = $response1->json('data.order_id');

        // Second request with same idempotency key
        $response2 = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/orders', $requestData);

        $response2->assertStatus(200); // Should return cached response
        $orderId2 = $response2->json('data.order_id');

        $this->assertEquals($orderId1, $orderId2);
        $this->assertDatabaseCount('orders', 1); // Only one order should be created
    }

    public function test_can_retrieve_order_status(): void
    {
        $hold = $this->createStockHold(1);
        $order = $this->createOrderFromHold($hold);

        $response = $this->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'amount',
                    'product_id',
                    'quantity',
                    'customer_email',
                    'created_at'
                ]
            ]);
    }
}
