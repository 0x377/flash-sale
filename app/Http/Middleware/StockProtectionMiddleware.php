<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StockProtectionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only apply to hold creation requests
        if (!$this->isHoldCreationRequest($request)) {
            return $next($request);
        }

        $productId = $request->input('product_id');
        $quantity = $request->input('quantity', 1);

        // Validate stock availability before proceeding
        if (!$this->validateStockAvailability($productId, $quantity)) {
            return response()->json([
                'error' => 'insufficient_stock',
                'message' => 'Requested quantity not available',
                'product_id' => $productId,
                'requested_quantity' => $quantity
            ], 422);
        }

        return $next($request);
    }

    private function isHoldCreationRequest(Request $request): bool
    {
        return $request->isMethod('POST') && 
               $request->routeIs('api.holds.store') &&
               $request->has(['product_id', 'quantity']);
    }

    private function validateStockAvailability(int $productId, int $quantity): bool
    {
        try {
            $availableStock = DB::transaction(function () use ($productId, $quantity) {
                return DB::table('products')
                    ->where('id', $productId)
                    ->where('is_active', true)
                    ->where('available_stock', '>=', $quantity)
                    ->value('available_stock');
            }, 3); // 3 retry attempts for deadlocks

            return $availableStock !== null && $availableStock >= $quantity;
        } catch (\Exception $e) {
            Log::error('Stock validation failed', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}
