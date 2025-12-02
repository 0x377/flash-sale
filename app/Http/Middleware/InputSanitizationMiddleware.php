<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InputSanitizationMiddleware
{
    private array $htmlTags = ['<', '>', '&lt;', '&gt;', 'script', 'iframe', 'object', 'embed'];
    private array $sqlKeywords = ['select', 'insert', 'update', 'delete', 'drop', 'union', '--', '/*', '*/', 'waitfor', 'delay'];

    public function handle(Request $request, Closure $next)
    {
        $this->sanitizeRequest($request);
        return $next($request);
    }

    private function sanitizeRequest(Request $request): void
    {
        // Sanitize all input data
        $inputs = $request->all();
        
        array_walk_recursive($inputs, function (&$value, $key) {
            if (is_string($value)) {
                $value = $this->sanitizeString($value);
            }
        });
        
        $request->merge($inputs);
    }

    private function sanitizeString(string $value): string
    {
        // Remove HTML tags
        $value = strip_tags($value);
        
        // Escape special characters
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        
        // Trim whitespace
        $value = trim($value);
        
        // Check for SQL injection patterns
        if ($this->containsSqlInjection($value)) {
            throw new \Illuminate\Validation\ValidationException(
                validator([], []), 
                response()->json([
                    'error' => 'invalid_input',
                    'message' => 'Invalid input detected',
                    'timestamp' => now()->toISOString()
                ], 422)
            );
        }
        
        return $value;
    }

    private function containsSqlInjection(string $value): bool
    {
        $lowerValue = Str::lower($value);
        
        foreach ($this->sqlKeywords as $keyword) {
            if (Str::contains($lowerValue, $keyword)) {
                // Check if it's part of a valid string or actual SQL
                if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $lowerValue)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
