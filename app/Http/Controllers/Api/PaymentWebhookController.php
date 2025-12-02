<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentWebhookController extends Controller
{
    protected PaymentWebhookService $webhookService;

    // Inject webhook service
    public function __construct(PaymentWebhookService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Handle payment webhook with idempotency
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|uuid|exists:orders,id',
            'payment_status' => 'required|in:success,failed,pending',
            'payment_reference' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'timestamp' => 'required|date',
            'metadata' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            Log::warning('Payment webhook validation failed', [
                'errors' => $validator->errors()->toArray(),
                'payload' => $request->all()
            ]);

            return response()->json([
                'error' => 'validation_failed',
                'message' => 'Invalid webhook payload',
                'errors' => $validator->errors()
            ], 422);
        }

        $idempotencyKey = $request->header('Idempotency-Key') 
            ?: $request->header('X-Idempotency-Key')
            ?: $request->input('idempotency_key');

        if (!$idempotencyKey) {
            return response()->json([
                'error' => 'idempotency_key_required',
                'message' => 'Idempotency key is required for payment webhooks'
            ], 422);
        }

        $webhookData = $request->all();
        $webhookData['idempotency_key'] = $idempotencyKey;
        $webhookData['ip_address'] = $request->ip();
        $webhookData['user_agent'] = $request->userAgent();

        Log::info('Payment webhook received', [
            'order_id' => $webhookData['order_id'],
            'payment_status' => $webhookData['payment_status'],
            'idempotency_key' => $idempotencyKey,
            'ip' => $request->ip()
        ]);

        try {
            $result = $this->webhookService->processWebhook($webhookData);

            if (!$result['success']) {
                Log::error('Payment webhook processing failed', [
                    'order_id' => $webhookData['order_id'],
                    'error' => $result['error']
                ]);

                return response()->json([
                    'error' => $result['error_type'] ?? 'webhook_processing_failed',
                    'message' => $result['message'] ?? 'Failed to process webhook'
                ], 422);
            }

            Log::info('Payment webhook processed successfully', [
                'order_id' => $webhookData['order_id'],
                'payment_status' => $webhookData['payment_status'],
                'order_status' => $result['order_status'],
                'idempotency_key' => $idempotencyKey
            ]);

            return response()->json([
                'data' => [
                    'processed' => true,
                    'order_id' => $webhookData['order_id'],
                    'order_status' => $result['order_status'],
                    'payment_status' => $webhookData['payment_status'],
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Payment webhook processing error', [
                'order_id' => $webhookData['order_id'],
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'internal_server_error',
                'message' => 'Failed to process payment webhook'
            ], 500);
        }
    }
}
