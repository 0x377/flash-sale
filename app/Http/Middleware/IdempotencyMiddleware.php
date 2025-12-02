<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next, string $resourceType): Response
    {
        // Only apply to mutating requests
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $next($request);
        }

        $idempotencyKey = $request->header('Idempotency-Key') 
            ?: $request->header('X-Idempotency-Key')
            ?: $request->input('idempotency_key');

        if (!$idempotencyKey) {
            return $next($request);
        }

        // Normalize the key
        $idempotencyKey = Str::lower(trim($idempotencyKey));
        $lockKey = "idempotency:lock:{$resourceType}:{$idempotencyKey}";

        return Cache::lock($lockKey, 10)->block(5, function() use ($request, $next, $idempotencyKey, $resourceType) {
            return DB::transaction(function () use ($request, $next, $idempotencyKey, $resourceType) {
                $cacheKey = "idempotency:{$resourceType}:{$idempotencyKey}";

                // Check if we have a cached response
                if (Cache::has($cacheKey)) {
                    $cachedResponse = Cache::get($cacheKey);
                    
                    Log::info('Idempotency cache hit', [
                        'key' => $idempotencyKey,
                        'resource_type' => $resourceType,
                        'path' => $request->path()
                    ]);

                    return response()
                        ->json($cachedResponse['data'])
                        ->setStatusCode($cachedResponse['status_code'])
                        ->withHeaders($cachedResponse['headers'] ?? []);
                }

                // Store original request for potential replay
                $requestFingerprint = $this->getRequestFingerprint($request);

                // Process the request
                $response = $next($request);

                // Cache successful responses for mutating operations
                if ($response->isSuccessful()) {
                    $this->cacheResponse(
                        $cacheKey,
                        $response,
                        $requestFingerprint,
                        $resourceType,
                        $idempotencyKey
                    );
                }

                return $response;
            });
        }, function() {
            return response()->json([
                'error' => 'concurrent_idempotency_request',
                'message' => 'Another request with the same idempotency key is being processed',
                'retry_after' => 1
            ], 409);
        });
    }

    private function getRequestFingerprint(Request $request): string
    {
        return md5(implode('|', [
            $request->method(),
            $request->path(),
            json_encode($request->all()),
            $request->header('Authorization', '')
        ]));
    }

    private function cacheResponse(
        string $cacheKey, 
        Response $response, 
        string $requestFingerprint,
        string $resourceType,
        string $idempotencyKey
    ): void {
        $cacheData = [
            'data' => json_decode($response->getContent(), true),
            'status_code' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'request_fingerprint' => $requestFingerprint,
            'cached_at' => now()->toISOString(),
            'resource_type' => $resourceType,
            'idempotency_key' => $idempotencyKey
        ];

        // Cache for longer duration for different resource types
        $ttl = match($resourceType) {
            'payment_webhook' => 86400, // 24 hours for payments
            'order_creation' => 3600,   // 1 hour for orders
            'stock_hold' => 300,        // 5 minutes for holds
            default => 900              // 15 minutes default
        };

        Cache::put($cacheKey, $cacheData, $ttl);

        Log::debug('Idempotency response cached', [
            'key' => $cacheKey,
            'resource_type' => $resourceType,
            'ttl' => $ttl
        ]);
    }
}
