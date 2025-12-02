<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('production')) {
            $signature = $request->header('X-Webhook-Signature');
            $secret = config('services.webhook.secret');
            
            if (!$signature || !$secret) {
                Log::warning('Webhook signature verification failed', [
                    'reason' => 'missing_signature_or_secret',
                    'has_signature' => !empty($signature),
                    'has_secret' => !empty($secret)
                ]);
                
                return response()->json([
                    'error' => 'invalid_signature',
                    'message' => 'Webhook signature verification failed',
                    'timestamp' => now()->toISOString()
                ], 401);
            }
            
            $payload = $request->getContent();
            $computedSignature = hash_hmac('sha256', $payload, $secret);
            
            if (!hash_equals($signature, $computedSignature)) {
                Log::warning('Webhook signature mismatch', [
                    'received' => $signature,
                    'computed' => $computedSignature
                ]);
                
                return response()->json([
                    'error' => 'invalid_signature',
                    'message' => 'Webhook signature verification failed',
                    'timestamp' => now()->toISOString()
                ], 401);
            }
        }
        
        return $next($request);
    }
}
