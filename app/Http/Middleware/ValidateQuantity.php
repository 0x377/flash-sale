<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateQuantity
{
    public function handle(Request $request, Closure $next)
    {
        $quantity = $request->input('quantity', 1);
        $maxQuantity = config('api.security.max_hold_quantity', 10);
        
        if (!is_numeric($quantity) || $quantity < 1 || $quantity > $maxQuantity) {
            return response()->json([
                'error' => 'invalid_quantity',
                'message' => "Quantity must be between 1 and {$maxQuantity}",
                'timestamp' => now()->toISOString()
            ], 422);
        }
        
        return $next($request);
    }
}
