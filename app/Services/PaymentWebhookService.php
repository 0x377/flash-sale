<?php
// app/Services/PaymentWebhookService.php

namespace App\Services;

use App\Models\IdempotencyKey;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentWebhookService
{
    public function handleWebhook(array $data, string $idempotencyKey)
    {
        // Check for existing idempotency key
        $existingKey = IdempotencyKey::where('key', $idempotencyKey)->first();

        if ($existingKey) {
            return response()->json($existingKey->response);
        }

        // Create idempotency key record
        $idempotencyRecord = IdempotencyKey::create([
            'id' => Str::uuid(),
            'key' => $idempotencyKey,
            'request_params' => $data,
            'processed_at' => now(),
        ]);

        DB::beginTransaction();

        try {
            // Process webhook
            $orderId = $data['order_id'] ?? null;
            $paymentStatus = $data['status'] ?? null;

            if (!$orderId) {
                throw new \Exception('Order ID is required');
            }

            // Find order (might not exist yet if webhook arrives before order creation)
            $order = Order::where('id', $orderId)->first();

            if (!$order) {
                // Store webhook for later processing
                $idempotencyRecord->update([
                    'resource_type' => 'pending_webhook',
                    'response' => ['status' => 'queued', 'message' => 'Order not found yet, queued for processing']
                ]);

                DB::commit();
                return response()->json(['status' => 'queued', 'message' => 'Order not found yet']);
            }

            // Update order status based on payment status
            if ($paymentStatus === 'success') {
                $order->markAsPaid();
                // Stock is already held, no need to adjust
            } else {
                $order->markAsFailed();
                // Release the stock hold
                app(StockService::class)->releaseHold($order->stock_hold_id);
            }

            // Update idempotency record
            $response = ['status' => 'processed', 'order_id' => $order->id, 'order_status' => $order->status];
            $idempotencyRecord->update([
                'resource_type' => Order::class,
                'resource_id' => $order->id,
                'response' => $response
            ]);

            DB::commit();

            return response()->json($response);

        } catch (\Exception $e) {
            DB::rollBack();
            
            $errorResponse = ['status' => 'error', 'message' => $e->getMessage()];
            $idempotencyRecord->update(['response' => $errorResponse]);
            
            return response()->json($errorResponse, 400);
        }
    }

    public function processPendingWebhooksForOrder(string $orderId): void
    {
        $pendingWebhooks = IdempotencyKey::where('resource_type', 'pending_webhook')
            ->whereJsonContains('request_params->order_id', $orderId)
            ->get();

        foreach ($pendingWebhooks as $webhook) {
            $this->handleWebhook($webhook->request_params, $webhook->key);
        }
    }
}
