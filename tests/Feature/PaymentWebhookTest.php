<?php

namespace Tests\Feature;

use Tests\Feature\FlashSaleTestCase;
use App\Models\Order;
use App\Models\PaymentWebhook;
use Illuminate\Support\Facades\Queue;

class PaymentWebhookTest extends FlashSaleTestCase
{
    public function test_successful_payment_webhook(): void
    {
        $hold = $this->createStockHold(1);
        $order = $this->createOrderFromHold($hold);

        $idempotencyKey = $this->generateIdempotencyKey();

        $response = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/payments/webhook', [
            'order_id' => $order->id,
            'payment_status' => 'success',
            'payment_reference' => 'pay_' . uniqid(),
            'amount' => $order->total_amount,
            'currency' => 'USD',
            'timestamp' => now()->toISOString(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.processed', true)
            ->assertJsonPath('data.order_status', 'paid');

        // Order should be marked as paid
        $this->assertEquals('paid', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_failed_payment_webhook(): void
    {
        $hold = $this->createStockHold(1);
        $order = $this->createOrderFromHold($hold);

        $response = $this->postJson('/api/payments/webhook', [
            'order_id' => $order->id,
            'payment_status' => 'failed',
            'payment_reference' => 'pay_' . uniqid(),
            'amount' => $order->total_amount,
            'currency' => 'USD',
            'timestamp' => now()->toISOString(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.order_status', 'failed');

        // Order should be marked as failed and stock released
        $this->assertEquals('failed', $order->fresh()->status);
        $this->assertEquals('expired', $hold->fresh()->status);
    }

    public function test_webhook_idempotency(): void
    {
        $hold = $this->createStockHold(1);
        $order = $this->createOrderFromHold($hold);
        $idempotencyKey = $this->generateIdempotencyKey();

        $webhookData = [
            'order_id' => $order->id,
            'payment_status' => 'success',
            'payment_reference' => 'pay_' . uniqid(),
            'amount' => $order->total_amount,
            'currency' => 'USD',
            'timestamp' => now()->toISOString(),
        ];

        // First webhook
        $response1 = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/payments/webhook', $webhookData);

        $response1->assertStatus(200);
        $orderStatus1 = $order->fresh()->status;

        // Second webhook with same idempotency key
        $response2 = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/payments/webhook', $webhookData);

        $response2->assertStatus(200);
        $orderStatus2 = $order->fresh()->status;

        // Order status should not change on duplicate webhook
        $this->assertEquals($orderStatus1, $orderStatus2);
        
        // Only one webhook should be processed
        $this->assertDatabaseCount('payment_webhooks', 1);
    }

    public function test_webhook_arriving_before_order_creation(): void
    {
        $orderId = (string) \Illuminate\Support\Str::uuid();
        $idempotencyKey = $this->generateIdempotencyKey();

        // Send webhook for non-existent order
        $webhookResponse = $this->withHeaders([
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/payments/webhook', [
            'order_id' => $orderId,
            'payment_status' => 'success',
            'payment_reference' => 'pay_early_' . uniqid(),
            'amount' => 99.99,
            'currency' => 'USD',
            'timestamp' => now()->toISOString(),
        ]);

        $webhookResponse->assertStatus(200);

        // Now create the order
        $hold = $this->createStockHold(1);
        $order = $this->createOrderFromHold($hold, ['id' => $orderId]);

        // The webhook should eventually process and mark the order as paid
        // This might require a retry mechanism or queue processing
        $this->assertEquals('paid', $order->fresh()->status);
    }

    public function test_webhook_validation(): void
    {
        $response = $this->postJson('/api/payments/webhook', [
            'order_id' => 'invalid-uuid',
            'payment_status' => 'invalid-status',
            'amount' => -100,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_id', 'payment_status', 'amount', 'currency', 'timestamp']);
    }

    public function test_webhook_without_idempotency_key(): void
    {
        $hold = $this->createStockHold(1);
        $order = $this->createOrderFromHold($hold);

        $response = $this->postJson('/api/payments/webhook', [
            'order_id' => $order->id,
            'payment_status' => 'success',
            'payment_reference' => 'pay_' . uniqid(),
            'amount' => $order->total_amount,
            'currency' => 'USD',
            'timestamp' => now()->toISOString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['error', 'message']);
    }
}