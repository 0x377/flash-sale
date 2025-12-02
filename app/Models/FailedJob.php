<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class FailedJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'connection',
        'queue',
        'payload',
        'exception',
        'failure_reason',
        'context',
        'failed_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'context' => 'array',
        'failed_at' => 'datetime'
    ];

    /**
     * Accessors & Mutators
     */
    protected function parsedException(): Attribute
    {
        return Attribute::make(
            get: function () {
                $exception = $this->exception;
                
                // Extract basic information from the exception
                preg_match('/Exception: (.*?) in (.*?):(\d+)/', $exception, $matches);
                
                return [
                    'message' => $matches[1] ?? 'Unknown error',
                    'file' => $matches[2] ?? 'Unknown file',
                    'line' => $matches[3] ?? 0,
                    'full_trace' => $exception
                ];
            }
        );
    }

    protected function jobName(): Attribute
    {
        return Attribute::make(
            get: function () {
                $payload = $this->payload;
                $displayName = $payload['displayName'] ?? 'Unknown Job';
                
                // Extract the actual job class name
                if (isset($payload['data']['command'])) {
                    $command = unserialize($payload['data']['command']);
                    return get_class($command);
                }
                
                return $displayName;
            }
        );
    }

    protected function ageInMinutes(): Attribute
    {
        return Attribute::make(
            get: fn () => now()->diffInMinutes($this->failed_at)
        );
    }

    /**
     * Scopes
     */
    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('failed_at', '>=', now()->subHours($hours));
    }

    public function scopeForQueue($query, $queue)
    {
        return $query->where('queue', $queue);
    }

    public function scopeForConnection($query, $connection)
    {
        return $query->where('connection', $connection);
    }

    public function scopeWithFailureReason($query, $reason)
    {
        return $query->where('failure_reason', 'like', "%{$reason}%");
    }

    /**
     * Business Logic Methods
     */
    public function retry(): bool
    {
        try {
            // This would contain the actual retry logic
            // In a real implementation, you might dispatch the job again
            
            \Log::info('Retrying failed job', [
                'job_uuid' => $this->uuid,
                'job_name' => $this->job_name
            ]);

            // For now, we'll just mark it as potentially retried
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to retry job', [
                'job_uuid' => $this->uuid,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    public function getContextData(): array
    {
        $context = $this->context ?? [];
        
        // Add additional context from the payload
        $payload = $this->payload;
        
        if (isset($payload['data']['command'])) {
            try {
                $command = unserialize($payload['data']['command']);
                $context['job_data'] = method_exists($command, 'getData') ? $command->getData() : [];
            } catch (\Exception $e) {
                $context['job_data'] = ['error' => 'Could not unserialize command'];
            }
        }
        
        return $context;
    }

    /**
     * Static Methods
     */
    public static function getFailureStats(): array
    {
        $total = self::count();
        $recent = self::recent(24)->count();
        
        $queueBreakdown = self::groupBy('queue')
            ->selectRaw('queue, COUNT(*) as count')
            ->get()
            ->pluck('count', 'queue')
            ->toArray();

        $commonReasons = self::whereNotNull('failure_reason')
            ->groupBy('failure_reason')
            ->selectRaw('failure_reason, COUNT(*) as count')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get()
            ->pluck('count', 'failure_reason')
            ->toArray();

        return [
            'total_failures' => $total,
            'recent_failures' => $recent,
            'queue_breakdown' => $queueBreakdown,
            'common_reasons' => $commonReasons,
            'failure_rate' => $total > 0 ? round(($recent / $total) * 100, 2) : 0,
        ];
    }

    public static function cleanupOldFailures(int $days = 30): int
    {
        return self::where('failed_at', '<=', now()->subDays($days))->delete();
    }
}
