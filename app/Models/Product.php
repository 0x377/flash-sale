<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'price',
        'initial_stock',
        'available_stock',
        'is_active',
        'metadata'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'initial_stock' => 'integer',
        'available_stock' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    protected $attributes = [
        'is_active' => true,
        'available_stock' => 0,
        'initial_stock' => 0
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($product) {
            // Invalidate cache when product is updated
            // Cache::tags(["product:{$product->id}", 'products'])->flush();
            if (Cache::supportsTags()) {
                Cache::tags(["product:{$product->id}", 'products'])->flush();
            } else {
                Cache::flush(); // or only clear specific keys if you want
            }
        });

        static::deleted(function ($product) {
            // Invalidate cache when product is deleted
            Cache::tags(["product:{$product->id}", 'products'])->flush();
        });
    }

    /**
     * Relationships
     */
    public function stockHolds()
    {
        return $this->hasMany(StockHold::class);
    }

    public function activeHolds()
    {
        return $this->hasMany(StockHold::class)
            ->where('status', 'pending')
            ->where('expires_at', '>', now());
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function paidOrders()
    {
        return $this->hasMany(Order::class)->where('status', 'paid');
    }

    /**
     * Accessors & Mutators
     */
    protected function availableStock(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => max(0, $value),
            set: fn ($value) => max(0, $value)
        );
    }

    protected function initialStock(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => max(0, $value)
        );
    }

    protected function formattedPrice(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->price, 2)
        );
    }

    protected function isLowStock(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->available_stock <= 10
        );
    }

    protected function isOutOfStock(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->available_stock <= 0
        );
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInStock($query)
    {
        return $query->where('available_stock', '>', 0);
    }

    public function scopeLowStock($query, $threshold = 10)
    {
        return $query->where('available_stock', '<=', $threshold)
                    ->where('available_stock', '>', 0);
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('available_stock', '<=', 0);
    }

    /**
     * Business Logic Methods
     */
    public function calculateAvailableStock(): int
    {
        $heldStock = $this->activeHolds()->sum('quantity');
        return max(0, $this->available_stock - $heldStock);
    }

    public function canFulfillQuantity(int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        $availableStock = $this->calculateAvailableStock();
        return $availableStock >= $quantity;
    }

    public function reserveStock(int $quantity, int $holdDurationMinutes = 2): ?StockHold
    {
        if (!$this->canFulfillQuantity($quantity)) {
            return null;
        }

        return DB::transaction(function () use ($quantity, $holdDurationMinutes) {
            // Lock the product row for update
            $lockedProduct = self::where('id', $this->id)
                ->lockForUpdate()
                ->first();

            if (!$lockedProduct->canFulfillQuantity($quantity)) {
                return null;
            }

            // Create stock hold
            $hold = StockHold::create([
                'product_id' => $this->id,
                'quantity' => $quantity,
                'expires_at' => now()->addMinutes($holdDurationMinutes),
                'status' => 'pending'
            ]);

            // Note: We don't decrement available_stock here as it's handled by the database trigger
            // or application logic in the service layer

            return $hold;
        });
    }

    public function incrementStock(int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        $this->increment('available_stock', $quantity);
        $this->refresh();

        // Invalidate cache
        Cache::tags(["product:{$this->id}", 'products'])->flush();

        return true;
    }

    public function decrementStock(int $quantity): bool
    {
        if ($quantity <= 0 || $this->available_stock < $quantity) {
            return false;
        }

        $this->decrement('available_stock', $quantity);
        $this->refresh();

        // Invalidate cache
        Cache::tags(["product:{$this->id}", 'products'])->flush();

        return true;
    }

    public function getStockMetrics(): array
    {
        $cacheKey = "product:{$this->id}:metrics";
        
        return Cache::remember($cacheKey, 60, function () {
            $totalHolds = $this->stockHolds()->count();
            $activeHolds = $this->activeHolds()->count();
            $expiredHolds = $this->stockHolds()->where('status', 'expired')->count();
            $consumedHolds = $this->stockHolds()->where('status', 'consumed')->count();
            
            $totalOrders = $this->orders()->count();
            $paidOrders = $this->paidOrders()->count();
            
            $conversionRate = $totalHolds > 0 ? ($paidOrders / $totalHolds) * 100 : 0;

            return [
                'total_holds' => $totalHolds,
                'active_holds' => $activeHolds,
                'expired_holds' => $expiredHolds,
                'consumed_holds' => $consumedHolds,
                'total_orders' => $totalOrders,
                'paid_orders' => $paidOrders,
                'conversion_rate' => round($conversionRate, 2),
                'stock_utilization' => $this->initial_stock > 0 ? 
                    round((($this->initial_stock - $this->available_stock) / $this->initial_stock) * 100, 2) : 0,
            ];
        });
    }

    /**
     * Static Methods
     */
    public static function getFlashSaleProduct(): ?self
    {
        return Cache::remember('flash_sale_product', 300, function () {
            return self::active()->inStock()->first();
        });
    }

    public static function updateStockCache(int $productId): void
    {
        $product = self::find($productId);
        if ($product) {
            Cache::tags(["product:{$productId}", 'products'])->flush();
        }
    }
}
