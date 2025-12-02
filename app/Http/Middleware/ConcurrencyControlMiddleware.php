<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ConcurrencyControlMiddleware
{
    public function handle(Request $request, Closure $next, string $resource, int $maxConcurrent = 5): Response
    {
        $identifier = $this->getConcurrencyIdentifier($request, $resource);
        $semaphoreKey = "concurrency:semaphore:{$identifier}";

        $acquired = $this->acquireSemaphore($semaphoreKey, $maxConcurrent);

        if (!$acquired) {
            Log::warning('Concurrency limit exceeded', [
                'resource' => $resource,
                'identifier' => $identifier,
                'max_concurrent' => $maxConcurrent,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'error' => 'too_many_concurrent_requests',
                'message' => 'Maximum concurrent requests exceeded for this resource',
                'retry_after' => 1,
                'resource' => $resource
            ], 429);
        }

        $startTime = microtime(true);

        try {
            $response = $next($request);
            
            Log::debug('Concurrent request processed', [
                'resource' => $resource,
                'processing_time' => microtime(true) - $startTime,
                'status' => $response->getStatusCode()
            ]);

            return $response;
        } finally {
            $this->releaseSemaphore($semaphoreKey);
        }
    }

    private function getConcurrencyIdentifier(Request $request, string $resource): string
    {
        $baseId = $request->user()?->id ?: $request->ip();
        
        return match($resource) {
            'stock_hold' => "stock_hold:{$baseId}:" . ($request->input('product_id') ?: 'global'),
            'order_creation' => "order:{$baseId}",
            'payment_webhook' => "webhook:{$request->getClientIp()}",
            'product_view' => "product_view:{$baseId}",
            default => "global:{$baseId}"
        };
    }

    private function acquireSemaphore(string $key, int $maxConcurrent): bool
    {
        $luaScript = <<<'LUA'
            local key = KEYS[1]
            local max = tonumber(ARGV[1])
            local member = ARGV[2]
            local ttl = tonumber(ARGV[3])
            local now = tonumber(ARGV[4])
            
            -- Remove expired members
            redis.call('ZREMRANGEBYSCORE', key, 0, now - ttl)
            
            -- Get current count
            local count = redis.call('ZCARD', key)
            
            if count >= max then
                return 0
            end
            
            -- Add new member with timestamp as score
            redis.call('ZADD', key, now, member)
            redis.call('EXPIRE', key, ttl)
            return 1
LUA;

        try {
            return (bool) Cache::store('redis-locks')->connection()->eval(
                $luaScript,
                1,
                $key,
                $maxConcurrent,
                uniqid('', true),
                30, // 30 second TTL
                microtime(true)
            );
        } catch (\Exception $e) {
            Log::error('Semaphore acquisition failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function releaseSemaphore(string $key): void
    {
        try {
            Cache::store('redis-locks')->connection()->zrem($key, ...Cache::store('redis-locks')->connection()->zrange($key, 0, -1));
        } catch (\Exception $e) {
            Log::error('Semaphore release failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }
    }
}
