<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RequestFingerprint
{
    /**
     * Handle the incoming request
     */
    public function handle(Request $request, Closure $next)
    {
        // Generate request fingerprint
        $fingerprint = $this->generateFingerprint($request);
        
        // Store fingerprint in request for later use
        $request->attributes->set('request_fingerprint', $fingerprint);
        
        // Check for suspicious activity
        $this->checkSuspiciousActivity($request, $fingerprint);
        
        // Track request for rate limiting and analytics
        $this->trackRequest($request, $fingerprint);
        
        // Process the request
        $response = $next($request);
        
        // Add fingerprint to response headers
        return $this->addFingerprintHeader($response, $fingerprint);
    }

    /**
     * Generate a unique fingerprint for the request
     */
    private function generateFingerprint(Request $request): string
    {
        $components = [];
        
        // IP address (first 3 octets for privacy)
        $ip = $request->ip();
        if ($ip) {
            $ipParts = explode('.', $ip);
            if (count($ipParts) === 4) {
                $components[] = implode('.', array_slice($ipParts, 0, 3)) . '.x';
            }
        }
        
        // User agent hash
        $userAgent = $request->userAgent();
        if ($userAgent) {
            $components[] = substr(md5($userAgent), 0, 8);
        }
        
        // Accept language
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            $components[] = substr(md5($acceptLanguage), 0, 4);
        }
        
        // Time window (15 minute intervals)
        $timeWindow = floor(time() / 900); // 900 seconds = 15 minutes
        $components[] = $timeWindow;
        
        // Generate fingerprint
        $fingerprint = implode('|', $components);
        
        // Add randomness to prevent fingerprint collisions
        $fingerprint .= '|' . Str::random(4);
        
        return hash('sha256', $fingerprint);
    }

    /**
     * Check for suspicious activity
     */
    private function checkSuspiciousActivity(Request $request, string $fingerprint): void
    {
        $key = "fingerprint:activity:{$fingerprint}";
        
        // Get existing activity count
        $activity = Cache::get($key, [
            'count' => 0,
            'first_seen' => now()->toISOString(),
            'last_seen' => now()->toISOString(),
            'endpoints' => [],
        ]);
        
        // Update activity
        $activity['count']++;
        $activity['last_seen'] = now()->toISOString();
        $activity['endpoints'][] = [
            'method' => $request->method(),
            'path' => $request->path(),
            'timestamp' => now()->toISOString(),
        ];
        
        // Keep only last 50 endpoints
        if (count($activity['endpoints']) > 50) {
            $activity['endpoints'] = array_slice($activity['endpoints'], -50);
        }
        
        // Store updated activity
        Cache::put($key, $activity, 3600); // Store for 1 hour
        
        // Check for suspicious patterns
        $this->detectSuspiciousPatterns($request, $fingerprint, $activity);
    }

    /**
     * Detect suspicious patterns
     */
    private function detectSuspiciousPatterns(Request $request, string $fingerprint, array $activity): void
    {
        $suspicious = false;
        $reason = '';
        
        // Too many requests in short time
        if ($activity['count'] > 100) {
            $suspicious = true;
            $reason = 'High request volume';
        }
        
        // Too many different endpoints
        $uniqueEndpoints = collect($activity['endpoints'])
            ->pluck('path')
            ->unique()
            ->count();
        
        if ($uniqueEndpoints > 20) {
            $suspicious = true;
            $reason = 'Accessing too many different endpoints';
        }
        
        // Rapid fire requests (more than 10 requests in 10 seconds)
        $recentRequests = collect($activity['endpoints'])
            ->filter(fn($req) => strtotime($req['timestamp']) > time() - 10)
            ->count();
        
        if ($recentRequests > 10) {
            $suspicious = true;
            $reason = 'Rapid fire requests detected';
        }
        
        // Log if suspicious
        if ($suspicious) {
            Log::warning('Suspicious activity detected', [
                'fingerprint' => $fingerprint,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'reason' => $reason,
                'request_count' => $activity['count'],
                'unique_endpoints' => $uniqueEndpoints,
                'recent_requests' => $recentRequests,
                'current_endpoint' => $request->path(),
                'timestamp' => now()->toISOString(),
            ]);
            
            // Store in suspicious activities cache
            $suspiciousKey = "fingerprint:suspicious:{$fingerprint}";
            Cache::put($suspiciousKey, [
                'fingerprint' => $fingerprint,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'reason' => $reason,
                'detected_at' => now()->toISOString(),
                'request_details' => $activity,
            ], 86400); // Store for 24 hours
        }
    }

    /**
     * Track request for analytics
     */
    private function trackRequest(Request $request, string $fingerprint): void
    {
        $trackingData = [
            'fingerprint' => $fingerprint,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
            'query_params' => array_keys($request->query()),
        ];
        
        // Store in cache for batch processing
        $key = "tracking:requests:" . date('Y-m-d-H');
        $requests = Cache::get($key, []);
        $requests[] = $trackingData;
        
        // Keep only last 1000 requests per hour
        if (count($requests) > 1000) {
            $requests = array_slice($requests, -1000);
        }
        
        Cache::put($key, $requests, 7200); // Store for 2 hours
    }

    /**
     * Add fingerprint to response headers
     */
    private function addFingerprintHeader($response, string $fingerprint)
    {
        // Add fingerprint header (truncated for security)
        $response->headers->set('X-Request-Fingerprint', substr($fingerprint, 0, 16));
        
        // Add request ID if not already present
        if (!$response->headers->has('X-Request-ID')) {
            $response->headers->set('X-Request-ID', Str::uuid());
        }
        
        return $response;
    }

    /**
     * Get request statistics for a fingerprint
     */
    public static function getStats(string $fingerprint): array
    {
        $key = "fingerprint:activity:{$fingerprint}";
        $activity = Cache::get($key, []);
        
        $suspiciousKey = "fingerprint:suspicious:{$fingerprint}";
        $suspicious = Cache::get($suspiciousKey, null);
        
        return [
            'fingerprint' => $fingerprint,
            'activity' => $activity,
            'is_suspicious' => $suspicious !== null,
            'suspicious_details' => $suspicious,
        ];
    }

    /**
     * Clear fingerprint data (for testing/cleanup)
     */
    public static function clearFingerprint(string $fingerprint): bool
    {
        $keys = [
            "fingerprint:activity:{$fingerprint}",
            "fingerprint:suspicious:{$fingerprint}",
        ];
        
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        
        return true;
    }
}
