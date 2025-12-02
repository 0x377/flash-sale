<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogWebhookReceived
{
    public function handle(Request $request, Closure $next)
    {
        Log::channel('webhooks')->info('Webhook received', [
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'timestamp' => now()->toISOString()
        ]);
        
        return $next($request);
    }
}
