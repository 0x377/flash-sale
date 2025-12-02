<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class StockHold extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'product_id',
        'quantity',
        'session_id',
        'status',
        'expires_at',
        'consumed_at'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'status' => 'pending'
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
        });

        static::updated(function ($hold) {
            if ($hold->isDirty('status')) {
                Cache::tags(["hold:{$hold->id}"])->flush();
                
                // Invalidate product cache when hold status changes
                Cache::tags(["product:{$hold->product_id}", 'products'])->flush();
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

    public function order()
    {
        return $this->hasOne(Order::class);
    }

    /**
     * Accessors & Mutators
     */
    protected function isExpired(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->expires_at->isPast()
        );
    }

    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'pending' && !$this->is_expired
        );
    }

    protected function timeUntilExpiration(): Attribute
    {
        return Attribute::make(
            get: fn () => now()->diffInSeconds($this->expires_at, false)
        );
    }

    protected function formattedExpiresAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->expires_at->toISOString()
        );
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'pending')
                    ->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('status', 'expired')
              ->orWhere(function ($q2) {
                  $q2->where('status', 'pending')
                     ->where('expires_at', '<=', now());
              });
        });
    }

    public function scopeConsumed($query)
    {
        return $query->where('status', 'consumed');
    }

    public function scopeExpiringSoon($query, $minutes = 1)
    {
        return $query->where('status', 'pending')
                    ->where('expires_at', '<=', now()->addMinutes($minutes))
                    ->where('expires_at', '>', now());
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Business Logic Methods
     */
    public function markAsConsumed(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        return DB::transaction(function () {
            $updated = self::where('id', $this->id)
                ->where('status', 'pending')
                ->where('expires_at', '>', now())
                ->update([
                    'status' => 'consumed',
                    'consumed_at' => now(),
                    'updated_at' => now()
                ]);

            if ($updated) {
                $this->refresh();
                Cache::tags(["hold:{$this->id}"])->flush();
                return true;
            }

            return false;
        });
    }

    public function markAsExpired(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->update(['status' => 'expired']);
        Cache::tags(["hold:{$this->id}"])->flush();

        return true;
    }

    public function renew(int $additionalMinutes = 2): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $newExpiry = now()->addMinutes($additionalMinutes);
        
        $updated = $this->update([
            'expires_at' => $newExpiry,
            'updated_at' => now()
        ]);

        if ($updated) {
            Cache::tags(["hold:{$this->id}"])->flush();
        }

        return $updated;
    }

    public function releaseStock(): bool
    {
        if ($this->status === 'consumed') {
            return false;
        }

        return DB::transaction(function () {
            // Mark as expired and release the stock
            $updated = self::where('id', $this->id)
                ->where('status', 'pending')
                ->update([
                    'status' => 'expired',
                    'updated_at' => now()
                ]);

            if ($updated) {
                $this->refresh();
                Cache::tags(["hold:{$this->id}"])->flush();
                
                // Invalidate product cache
                Cache::tags(["product:{$this->product_id}", 'products'])->flush();
                
                return true;
            }

            return false;
        });
    }

    public function isValidForOrder(): bool
    {
        return $this->is_active && !$this->order;
    }

    /**
     * Static Methods
     */
    public static function expireOldHolds(): int
    {
        return DB::transaction(function () {
            $expiredHolds = self::where('status', 'pending')
                ->where('expires_at', '<=', now())
                ->get();

            $count = 0;

            foreach ($expiredHolds as $hold) {
                if ($hold->markAsExpired()) {
                    $count++;
                }
            }

            return $count;
        });
    }

    public static function cleanupExpiredHolds(int $daysOld = 7): int
    {
        return self::where('status', 'expired')
            ->where('updated_at', '<=', now()->subDays($daysOld))
            ->delete();
    }

    public static function getActiveHoldCount(int $productId): int
    {
        $cacheKey = "product:{$productId}:active_holds_count";
        
        return Cache::remember($cacheKey, 30, function () use ($productId) {
            return self::where('product_id', $productId)
                ->active()
                ->count();
        });
    }
}
