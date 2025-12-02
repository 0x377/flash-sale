<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Cache;

class IdempotencyKey extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'key',
        'resource_type',
        'resource_id',
        'request_params',
        'response',
        'response_code',
        'locked_at',
        'completed_at'
    ];

    protected $casts = [
        'request_params' => 'array',
        'response' => 'array',
        'locked_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
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

        static::updated(function ($idempotencyKey) {
            // Clear cache when idempotency key is updated
            Cache::forget("idempotency:{$idempotencyKey->key}:{$idempotencyKey->resource_type}");
        });
    }

    /**
     * Accessors & Mutators
     */
    protected function isLocked(): Attribute
    {
        return Attribute::make(
            get: fn () => !is_null($this->locked_at) && 
                         $this->locked_at->gt(now()->subMinutes(5))
        );
    }

    protected function isCompleted(): Attribute
    {
        return Attribute::make(
            get: fn () => !is_null($this->completed_at)
        );
    }

    protected function isExpired(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->locked_at && 
                         $this->locked_at->lt(now()->subMinutes(10))
        );
    }

    protected function ageInSeconds(): Attribute
    {
        return Attribute::make(
            get: fn () => now()->diffInSeconds($this->created_at)
        );
    }

    /**
     * Scopes
     */
    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('completed_at');
    }

    public function scopeLocked($query)
    {
        return $query->whereNotNull('locked_at')
                    ->where('locked_at', '>', now()->subMinutes(5));
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('locked_at')
                    ->where('locked_at', '<=', now()->subMinutes(10));
    }

    public function scopeForResource($query, $resourceType, $resourceId = null)
    {
        $query = $query->where('resource_type', $resourceType);
        
        if ($resourceId) {
            $query->where('resource_id', $resourceId);
        }
        
        return $query;
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Business Logic Methods
     */
    public function markAsLocked(): bool
    {
        return $this->update([
            'locked_at' => now(),
            'completed_at' => null
        ]);
    }

    public function markAsCompleted(array $response, int $responseCode): bool
    {
        return $this->update([
            'response' => $response,
            'response_code' => $responseCode,
            'completed_at' => now(),
            'locked_at' => null
        ]);
    }

    public function releaseLock(): bool
    {
        return $this->update(['locked_at' => null]);
    }

    public function getCachedResponse(): ?array
    {
        if (!$this->is_completed) {
            return null;
        }

        return [
            'data' => $this->response,
            'status_code' => $this->response_code,
            'cached_at' => $this->completed_at->toISOString()
        ];
    }

    public function isValidForRequest(array $currentParams): bool
    {
        if (!$this->is_completed) {
            return false;
        }

        // Verify that the request parameters match
        return $this->request_params === $currentParams;
    }

    /**
     * Static Methods
     */
    public static function findByKeyAndResource(string $key, string $resourceType): ?self
    {
        $cacheKey = "idempotency:{$key}:{$resourceType}";
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $resourceType) {
            return self::where('key', $key)
                ->where('resource_type', $resourceType)
                ->first();
        });
    }

    public static function createForKey(string $key, string $resourceType, array $requestParams = []): self
    {
        return self::create([
            'key' => $key,
            'resource_type' => $resourceType,
            'request_params' => $requestParams,
            'locked_at' => now()
        ]);
    }

    public static function cleanupExpiredKeys(int $hoursOld = 24): int
    {
        return self::where('created_at', '<=', now()->subHours($hoursOld))->delete();
    }

    public static function cleanupStaleLocks(): int
    {
        return self::whereNotNull('locked_at')
            ->where('locked_at', '<=', now()->subMinutes(10))
            ->update(['locked_at' => null]);
    }

    public static function getIdempotencyStats(): array
    {
        $cacheKey = 'idempotency_stats';
        
        return Cache::remember($cacheKey, 300, function () {
            $total = self::count();
            $completed = self::completed()->count();
            $pending = self::pending()->count();
            $locked = self::locked()->count();
            $expired = self::expired()->count();

            $resourceTypes = self::groupBy('resource_type')
                ->selectRaw('resource_type, COUNT(*) as count')
                ->get()
                ->pluck('count', 'resource_type')
                ->toArray();

            return [
                'total_keys' => $total,
                'completed_keys' => $completed,
                'pending_keys' => $pending,
                'locked_keys' => $locked,
                'expired_keys' => $expired,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 0,
                'resource_breakdown' => $resourceTypes,
            ];
        });
    }
}