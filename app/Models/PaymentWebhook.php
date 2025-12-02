<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Cache;

class PaymentWebhook extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'idempotency_key',
        'order_id',
        'payment_provider',
        'payment_reference',
        'status',
        'amount',
        'currency',
        'payload',
        'provider_response',
        'attempts',
        'processed_at',
        'next_retry_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
        'provider_response' => 'array',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $attributes = [
        'attempts' => 0,
        'currency' => 'USD'
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
    }

    /**
     * Relationships
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Accessors & Mutators
     */
    protected function isProcessed(): Attribute
    {
        return Attribute::make(
            get: fn () => !is_null($this->processed_at)
        );
    }

    protected function isSuccessful(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'success'
        );
    }

    protected function isFailed(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status === 'failed'
        );
    }

    protected function needsRetry(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->is_failed && 
                         $this->attempts < 3 && 
                         $this->next_retry_at?->isPast() !== false
        );
    }

    protected function formattedAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => number_format($this->amount, 2)
        );
    }

    /**
     * Scopes
     */
    public function scopeProcessed($query)
    {
        return $query->whereNotNull('processed_at');
    }

    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeNeedsRetry($query)
    {
        return $query->where('status', 'failed')
                    ->where('attempts', '<', 3)
                    ->where(function ($q) {
                        $q->whereNull('next_retry_at')
                          ->orWhere('next_retry_at', '<=', now());
                    });
    }

    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Business Logic Methods
     */
    public function markAsProcessed(string $status, array $providerResponse = []): bool
    {
        $updated = $this->update([
            'status' => $status,
            'provider_response' => $providerResponse,
            'processed_at' => now(),
            'attempts' => $this->attempts + 1,
            'updated_at' => now()
        ]);

        return $updated;
    }

    public function markForRetry(int $delayMinutes = 5): bool
    {
        $updated = $this->update([
            'next_retry_at' => now()->addMinutes($delayMinutes),
            'attempts' => $this->attempts + 1,
            'updated_at' => now()
        ]);

        return $updated;
    }

    public function shouldRetry(): bool
    {
        return $this->needs_retry && $this->attempts < 3;
    }

    public function getRetryDelay(): int
    {
        return match($this->attempts) {
            0 => 1,   // 1 minute
            1 => 5,   // 5 minutes
            2 => 15,  // 15 minutes
            default => 0
        };
    }

    /**
     * Static Methods
     */
    public static function findByOrderAndIdempotencyKey(string $orderId, string $idempotencyKey): ?self
    {
        $cacheKey = "webhook:order:{$orderId}:key:{$idempotencyKey}";
        
        return Cache::remember($cacheKey, 3600, function () use ($orderId, $idempotencyKey) {
            return self::where('order_id', $orderId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
        });
    }

    public static function processPendingWebhooks(): int
    {
        $webhooks = self::needsRetry()
            ->with('order')
            ->limit(50)
            ->get();

        $processed = 0;

        foreach ($webhooks as $webhook) {
            try {
                // Simulate webhook processing - in real implementation, this would call your webhook processor
                if ($webhook->order) {
                    $success = $webhook->processWebhook();
                    if ($success) {
                        $processed++;
                    }
                }
            } catch (\Exception $e) {
                // Log error but continue processing other webhooks
                \Log::error('Failed to process webhook', [
                    'webhook_id' => $webhook->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $processed;
    }

    public function processWebhook(): bool
    {
        // This would contain the actual webhook processing logic
        // For now, we'll simulate processing based on the payload
        
        $payload = $this->payload;
        $status = $payload['payment_status'] ?? 'failed';
        
        if ($status === 'success') {
            $success = $this->order->markAsPaid($this->payment_reference);
        } else {
            $success = $this->order->markAsFailed($status);
        }

        if ($success) {
            return $this->markAsProcessed($status, ['processed' => true]);
        }

        return $this->markForRetry($this->getRetryDelay());
    }

    public static function getWebhookStats(): array
    {
        $cacheKey = 'webhook_stats';
        
        return Cache::remember($cacheKey, 300, function () {
            $total = self::count();
            $processed = self::processed()->count();
            $successful = self::successful()->count();
            $failed = self::failed()->count();
            $pending = self::unprocessed()->count();

            return [
                'total_webhooks' => $total,
                'processed_webhooks' => $processed,
                'successful_webhooks' => $successful,
                'failed_webhooks' => $failed,
                'pending_webhooks' => $pending,
                'success_rate' => $processed > 0 ? round(($successful / $processed) * 100, 2) : 0,
                'processing_rate' => $total > 0 ? round(($processed / $total) * 100, 2) : 0,
            ];
        });
    }
}