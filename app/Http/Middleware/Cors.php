<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Cors
{
    /**
     * Allowed origins
     */
    private array $allowedOrigins = [];
    
    /**
     * Allowed methods
     */
    private array $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
    
    /**
     * Allowed headers
     */
    private array $allowedHeaders = [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-Request-ID',
        'Idempotency-Key',
        'X-Idempotency-Key',
        'Accept',
        'Origin',
        'X-CSRF-TOKEN',
        'X-API-Key',
    ];
    
    /**
     * Exposed headers
     */
    private array $exposedHeaders = [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'X-Request-ID',
        'X-Response-Time',
        'X-API-Version',
    ];
    
    /**
     * Max age for preflight requests
     */
    private int $maxAge = 86400; // 24 hours

    public function __construct()
    {
        // Load allowed origins from config
        $this->allowedOrigins = config('cors.allowed_origins', []);
        
        // Add current domain as allowed origin
        $currentDomain = config('app.url');
        if (!in_array($currentDomain, $this->allowedOrigins)) {
            $this->allowedOrigins[] = $currentDomain;
        }
        
        // For development, allow localhost
        if (app()->environment('local', 'development')) {
            $this->allowedOrigins = array_merge($this->allowedOrigins, [
                'http://localhost:8000',
                'http://127.0.0.1:8000',
            ]);
        }
    }

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next)
    {
        // Handle preflight requests
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest($request);
        }

        // Process the request
        $response = $next($request);

        // Add CORS headers to the response
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle preflight request
     */
    private function handlePreflightRequest(Request $request)
    {
        $origin = $request->header('Origin');
        
        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            Log::warning('CORS preflight blocked', [
                'origin' => $origin,
                'method' => $request->header('Access-Control-Request-Method'),
                'headers' => $request->header('Access-Control-Request-Headers'),
                'ip' => $request->ip(),
            ]);
            
            return response('', 403);
        }

        $response = response('', 204);
        
        // Add CORS headers for preflight
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
        $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));
        $response->headers->set('Access-Control-Max-Age', $this->maxAge);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        
        // Add Vary header for proper caching
        $response->headers->set('Vary', 'Origin');
        
        return $response;
    }

    /**
     * Add CORS headers to response
     */
    private function addCorsHeaders(Request $request, $response)
    {
        $origin = $request->header('Origin');
        
        // Check if origin is allowed
        if ($this->isOriginAllowed($origin)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            
            // Add Vary header for proper caching
            $response->headers->set('Vary', 'Origin');
        }
        
        // Add security headers
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Add timing allow origin for performance monitoring
        $response->headers->set('Timing-Allow-Origin', $origin ?? '*');
        
        return $response;
    }

    /**
     * Check if origin is allowed
     */
    private function isOriginAllowed(?string $origin): bool
    {
        if (!$origin) {
            return false;
        }

        // Allow all origins in development
        if (app()->environment('local', 'development') && config('cors.allow_all_origins', false)) {
            return true;
        }

        // Check against allowed origins
        foreach ($this->allowedOrigins as $allowedOrigin) {
            if ($origin === $allowedOrigin) {
                return true;
            }
            
            // Support wildcard subdomains
            if (str_contains($allowedOrigin, '*')) {
                $pattern = '/^' . str_replace('\*', '.*', preg_quote($allowedOrigin, '/')) . '$/';
                if (preg_match($pattern, $origin)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Log CORS violations
     */
    private function logViolation(Request $request, string $reason): void
    {
        Log::warning('CORS violation', [
            'origin' => $request->header('Origin'),
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'reason' => $reason,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
