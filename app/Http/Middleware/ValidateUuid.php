<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ValidateUuid
{
    public function handle(Request $request, Closure $next, $parameter = null)
    {
        $parameter = $parameter ?: $this->getUuidParameter($request);
        
        if ($parameter && $request->route($parameter)) {
            $uuid = $request->route($parameter);
            
            if (!Str::isUuid($uuid)) {
                return response()->json([
                    'error' => 'invalid_parameter',
                    'message' => 'Invalid UUID format',
                    'parameter' => $parameter,
                    'value' => $uuid,
                    'timestamp' => now()->toISOString()
                ], 422);
            }
        }
        
        return $next($request);
    }

    private function getUuidParameter(Request $request): ?string
    {
        $routeParameters = $request->route()->parameters();
        
        foreach ($routeParameters as $key => $value) {
            if (Str::contains($key, ['hold', 'order', 'webhook', 'uuid', 'id'])) {
                return $key;
            }
        }
        
        return null;
    }
}
