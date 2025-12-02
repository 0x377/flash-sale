<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class Order extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'product_id',
        'stock_hold_id',
        'quantity',
        'unit_price',
        'total_amount',
        'status',
        'customer_email',
        'customer_details',
        'paid_at',
        'cancelled_at',
        'webhook_idempotency_key',
        'payment_idempotency_key'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'customer_details' => 'array',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => 'pending',
        'quantity' => 1
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }

            // Calculate total amount if not set
            if (empty($model->total_amount) && $model->unit_price && $model->quantity) {
                $model->total_amount = $model->unit_price * $model->quantity;
            }
        });

        static::updated(function ($order) {
            if ($order->isDirty('status')) {
                Cache::tags(["order:{$order->id}"])->flush();
                
                // Invalidate product cache when order status changes
                Cache::tags(["product:{$order->product_id}", 'products'])->flush();
            }
        });
    }

    /**
     * Relationships
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockHold()
    {
        return $this->belongsTo(StockHold::class);
    }

    public function paymentWebhooks()
    {
        return $this->hasMany(PaymentWebhook::class);
    }

    /**
     * Accessors & Mutators
     */
    protected function isPaid(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'paid'
        );
    }

    protected function isPending(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'pending'
        );
    }

    protected function isCancelled(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'cancelled'
        );
    }

    protected function isFailed(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'failed'
        );
    }

    protected function formattedTotalAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->total_amount, 2)
        );
    }

    protected function customerName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->customer_details['name'] ?? null
        );
    }

    protected function paymentAge(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->paid_at ? now()->diffInMinutes($this->paid_at) : null
        );
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    public function scopeNeedsPayment($query)
    {
        return $query->where('status', 'pending')
                    ->where('created_at', '>=', now()->subMinutes(30)); // 30 min payment window
    }

    public function scopeForCustomer($query, $email)
    {
        return $query->where('customer_email', $email);
    }

    /**
     * Business Logic Methods
     */
    public function markAsPaid(?string $paymentReference = null): bool
    {
        if ($this->is_paid) {
            return true;
        }

        if ($this->is_cancelled || $this->is_failed) {
            return false;
        }

        return DB::transaction(function () use ($paymentReference) {
            $updated = self::where('id', $this->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'updated_at' => now()
                ]);

            if ($updated) {
                $this->refresh();
                
                // Mark the associated hold as consumed
                if ($this->stockHold) {
                    $this->stockHold->markAsConsumed();
                }

                Cache::tags(["order:{$this->id}"])->flush();
                return true;
            }

            return false;
        });
    }

    public function markAsFailed(?string $reason = null): bool
    {
        if ($this->is_failed) {
            return true;
        }

        return DB::transaction(function () use ($reason) {
            $updated = self::where('id', $this->id)
                ->whereIn('status', ['pending', 'paid']) // Can only fail pending or paid orders
                ->update([
                    'status' => 'failed',
                    'cancelled_at' => now(),
                    'updated_at' => now()
                ]);

            if ($updated) {
                $this->refresh();
                
                // Release the stock hold if it's still active
                if ($this->stockHold && $this->stockHold->is_active) {
                    $this->stockHold->releaseStock();
                }

                Cache::tags(["order:{$this->id}"])->flush();
                return true;
            }

            return false;
        });
    }

    public function markAsCancelled(?string $reason = null): bool
    {
        if ($this->is_cancelled) {
            return true;
        }

        return DB::transaction(function () use ($reason) {
            $updated = self::where('id', $this->id)
                ->where('status', 'pending') // Can only cancel pending orders
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'updated_at' => now()
                ]);

            if ($updated) {
                $this->refresh();

                // Release the stock hold
                if ($this->stockHold) {
                    $this->stockHold->releaseStock();
                }

                Cache::tags(["order:{$this->id}"])->flush();
                return true;
            }

            return false;
        });
    }

    public function canBePaid(): bool
    {
        return $this->is_pending && 
               $this->created_at->gte(now()->subMinutes(30)) && // Within 30 min window
               $this->stockHold?->is_active;
    }

    public function getPaymentStatus(): string
    {
        if ($this->is_paid) return 'paid';
        if ($this->is_cancelled) return 'cancelled';
        if ($this->is_failed) return 'failed';
        if ($this->canBePaid()) return 'awaiting_payment';

        return 'expired';
    }

    public function addWebhookIdempotencyKey(string $key): bool
    {
        return $this->update(['webhook_idempotency_key' => $key]);
    }

    public function addPaymentIdempotencyKey(string $key): bool
    {
        return $this->update(['payment_idempotency_key' => $key]);
    }

    /**
     * Static Methods
     */
    public static function cleanupExpiredOrders(int $hoursOld = 48): int
    {
        return DB::transaction(function () use ($hoursOld) {
            $expiredOrders = self::where('status', 'pending')
                ->where('created_at', '<=', now()->subHours(24)) // 24 hour expiry
                ->get();

            $count = 0;

            foreach ($expiredOrders as $order) {
                if ($order->markAsCancelled('auto_expired')) {
                    $count++;
                }
            }

            return $count;
        });
    }

    public static function getOrderStats(): array
    {
        $cacheKey = 'order_stats';

        return Cache::remember($cacheKey, 300, function () {
            $total = self::count();
            $pending = self::pending()->count();
            $paid = self::paid()->count();
            $cancelled = self::cancelled()->count();
            $failed = self::failed()->count();

            $revenue = self::paid()->sum('total_amount');
            $avgOrderValue = $paid > 0 ? $revenue / $paid : 0;

            return [
                'total_orders' => $total,
                'pending_orders' => $pending,
                'paid_orders' => $paid,
                'cancelled_orders' => $cancelled,
                'failed_orders' => $failed,
                'conversion_rate' => $total > 0 ? round(($paid / $total) * 100, 2) : 0,
                'total_revenue' => (float) $revenue,
                'average_order_value' => round($avgOrderValue, 2),
            ];
        });
    }
}
