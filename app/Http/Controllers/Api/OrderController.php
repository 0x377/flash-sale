<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    protected OrderService $orderService;

    // Inject OrderService here
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    /*
     * Create order from hold
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'hold_id' => 'required|uuid|exists:stock_holds,id',
            // 'customer_email' => 'required|email',
            // 'customer_details' => 'sometimes|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'validation_failed',
                'message' => 'Invalid request parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        $holdId = $request->input('hold_id');
        $customerEmail = $request->input('customer_email');
        $customerDetails = $request->input('customer_details', []);

        Log::info('Order creation attempt', [
            'hold_id' => $holdId,
            'customer_email' => $customerEmail
        ]);

        try {
            $order = DB::transaction(function () use ($holdId, $customerEmail, $customerDetails) {
                return $this->orderService->createOrderFromHold($holdId, $customerEmail, $customerDetails);
            });

            if (!$order) {
                return response()->json([
                    'error' => 'order_creation_failed',
                    'message' => 'Failed to create order from hold',
                    'hold_id' => $holdId
                ], 422);
            }

            Log::info('Order created successfully', [
                'order_id' => $order->id,
                'hold_id' => $holdId,
                'status' => $order->status
            ]);

            return response()->json([
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'amount' => $order->total_amount,
                    'product_id' => $order->product_id,
                    'quantity' => $order->quantity,
                    'created_at' => $order->created_at->toISOString()
                ],
                'meta' => [
                    'payment_required' => true,
                    'payment_timeout_minutes' => 30
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Order creation failed', [
                'hold_id' => $holdId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'order_creation_error',
                'message' => 'Failed to create order',
                'retry_after' => 1
            ], 500);
        }
    }

    /*
     * Get order status
     */
    public function show(string $orderId): JsonResponse
    {
        $order = $this->orderService->getOrder($orderId);

        if (!$order) {
            return response()->json([
                'error' => 'order_not_found',
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'data' => $order
        ]);
    }
}
