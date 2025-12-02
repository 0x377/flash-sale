<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockHoldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StockHoldController extends Controller
{
    protected StockHoldService $stockHoldService;

    // Inject service here
    public function __construct(StockHoldService $stockHoldService)
    {
        $this->stockHoldService = $stockHoldService;
    }

    /**
     * Create a stock hold with concurrency protection
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:10',
            'session_id' => 'sometimes|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'validation_failed',
                'message' => 'Invalid request parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        $productId  = $request->input('product_id');
        $quantity   = $request->input('quantity');
        $sessionId  = $request->input('session_id', $request->header('X-Session-ID'));

        Log::info('Stock hold creation attempt', [
            'product_id' => $productId,
            'quantity' => $quantity,
            'session_id' => $sessionId,
            'ip' => $request->ip()
        ]);

        try {
            $result = DB::transaction(function () use ($productId, $quantity, $sessionId) {
                return $this->stockHoldService->createHold($productId, $quantity, $sessionId);
            }, 3); // Retry up to 3 times for deadlocks

            if (!$result['success']) {
                return response()->json([
                    'error' => $result['error_type'] ?? 'hold_failed',
                    'message' => $result['message'] ?? 'Failed to create stock hold',
                    'product_id' => $productId,
                    'requested_quantity' => $quantity
                ], 422);
            }

            Log::info('Stock hold created successfully', [
                'hold_id' => $result['hold_id'],
                'product_id' => $productId,
                'quantity' => $quantity,
                'expires_at' => $result['expires_at']
            ]);

            return response()->json([
                'data' => [
                    'hold_id' => $result['hold_id'],
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'expires_at' => $result['expires_at'],
                    'expires_in_seconds' => $result['expires_in_seconds']
                ],
                'meta' => [
                    'timestamp' => now()->toISOString(),
                    'hold_duration_minutes' => 2
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Stock hold creation failed', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'internal_server_error',
                'message' => 'Failed to process stock hold request',
                'retry_after' => 1
            ], 500);
        }
    }

    /**
     * Get hold status
     */
    public function show(string $holdId): JsonResponse
    {
        $hold = $this->stockHoldService->getHold($holdId);

        if (!$hold) {
            return response()->json([
                'error' => 'hold_not_found',
                'message' => 'Stock hold not found or expired'
            ], 404);
        }

        return response()->json([
            'data' => $hold
        ]);
    }

    /**
     * Release a hold manually
     */
    public function destroy(string $holdId): JsonResponse
    {
        $released = $this->stockHoldService->releaseHold($holdId);

        if (!$released) {
            return response()->json([
                'error' => 'hold_release_failed',
                'message' => 'Failed to release stock hold'
            ], 422);
        }

        Log::info('Stock hold manually released', ['hold_id' => $holdId]);

        return response()->json([
            'data' => [
                'released' => true,
                'hold_id' => $holdId,
                'timestamp' => now()->toISOString()
            ]
        ]);
    }
}
