<?php
// app/Services/OrderService.php

namespace App\Services;

use App\Models\Order;
use App\Models\StockHold;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    public function createOrder(string $holdId): ?Order
    {
        return DB::transaction(function () use ($holdId) {
            // Get hold with lock
            $hold = StockHold::where('id', $holdId)
                ->lockForUpdate()
                ->with('product')
                ->first();

            if (!$hold || !$hold->isActive()) {
                return null;
            }

            // Mark hold as used
            $hold->markAsUsed();

            // Create order
            $order = Order::create([
                'id' => Str::uuid(),
                'product_id' => $hold->product_id,
                'stock_hold_id' => $holdId,
                'quantity' => $hold->quantity,
                'total_amount' => $hold->quantity * $hold->product->price,
                'status' => 'pending',
            ]);

            return $order;
        });
    }
}
