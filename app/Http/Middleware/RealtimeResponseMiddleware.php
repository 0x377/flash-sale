<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RealtimeResponseMiddleware
{
    public function handle(Request $request, Closure $next): Respone
    {
        $response = $next($request);

        // Add headers to prevent caching for real-time endpoints
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');

        return $response;
    }
}
