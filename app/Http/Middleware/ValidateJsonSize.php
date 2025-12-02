<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateJsonSize
{
    public function handle(Request $request, Closure $next, $maxSize = 1024)
    {
        $contentLength = $request->header('Content-Length');
        
        if ($contentLength && $contentLength > $maxSize * 1024) {
            return response()->json([
                'error' => 'payload_too_large',
                'message' => 'Request payload exceeds maximum size',
                'max_size_kb' => $maxSize,
                'actual_size_kb' => round($contentLength / 1024, 2),
                'timestamp' => now()->toISOString()
            ], 413);
        }
        
        // Also check actual content if Content-Length header is missing
        $content = $request->getContent();
        if (strlen($content) > $maxSize * 1024) {
            return response()->json([
                'error' => 'payload_too_large',
                'message' => 'Request payload exceeds maximum size',
                'max_size_kb' => $maxSize,
                'actual_size_kb' => round(strlen($content) / 1024, 2),
                'timestamp' => now()->toISOString()
            ], 413);
        }
        
        return $next($request);
    }
}
