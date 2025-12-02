<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SqlInjectionProtection
{
    /**
     * SQL keywords and patterns to detect
     */
    private array $sqlKeywords = [
        // SQL commands
        'select', 'insert', 'update', 'delete', 'drop', 'create', 'alter', 'truncate',
        'union', 'join', 'from', 'where', 'having', 'group by', 'order by',
        
        // SQL operators and functions
        'concat', 'substring', 'char', 'ascii', 'version', 'database', 'user',
        'sleep', 'benchmark', 'waitfor', 'delay',
        
        // SQL comments and separators
        '--', '/*', '*/', '#', ';', 
        
        // Dangerous patterns
        'xp_', 'sp_', 'exec', 'execute', 'sysobjects', 'syscolumns',
        'information_schema', 'pg_catalog', 'mysql.',
        
        // Union-based injection patterns
        'union all', 'union distinct',
        
        // Blind SQL patterns
        'if(', 'case when', 'elt(', 'strcmp',
    ];

    /**
     * SQL injection patterns (regex)
     */
    private array $sqlPatterns = [
        // Basic patterns
        '/\b(select|insert|update|delete|drop|create|alter)\b.*\b(from|into|table|database)\b/i',
        '/union.*select/i',
        '/\bor\b.*\b=\b.*\bor\b/i',
        '/\band\b.*\b=\b.*\band\b/i',
        '/exec(\s|\+)+(s|x)p\w+/ix',
        '/waitfor\s+delay/i',
        '/benchmark\s*\(/i',

        // Hex encoded patterns
        '/0x[0-9a-f]+/i',

        // Comment patterns
        '/--.*$/m',
        '/\/\*.*\*\//s',

        // Function call patterns
        '/\w+\s*\(\s*select/i',

        // System table references
        '/information_schema\.(tables|columns)/i',
        '/sys\.(tables|columns)/i',
    ];

    /**
     * Handle the incoming request
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip for certain routes if needed
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Check GET parameters
        $this->checkParameters($request->query(), 'GET');
        
        // Check POST parameters
        $this->checkParameters($request->post(), 'POST');
        
        // Check route parameters
        $this->checkRouteParameters($request);
        
        // Check headers (some attacks use headers)
        $this->checkHeaders($request);
        
        // Check request content for JSON/XML
        $this->checkRequestContent($request);

        return $next($request);
    }

    /**
     * Check parameters for SQL injection patterns
     */
    private function checkParameters(array $parameters, string $method): void
    {
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                $this->checkParameters($value, $method);
                continue;
            }

            if (is_string($value)) {
                $this->detectSqlInjection($key, $value, $method);
            }
        }
    }

    /**
     * Check route parameters
     */
    private function checkRouteParameters(Request $request): void
    {
        $routeParameters = $request->route()->parameters();
        
        foreach ($routeParameters as $key => $value) {
            if (is_string($value)) {
                $this->detectSqlInjection($key, $value, 'ROUTE');
            }
        }
    }

    /**
     * Check request headers
     */
    private function checkHeaders(Request $request): void
    {
        $suspiciousHeaders = [
            'User-Agent',
            'Referer',
            'X-Forwarded-For',
            'X-Real-IP',
        ];

        foreach ($suspiciousHeaders as $header) {
            if ($value = $request->header($header)) {
                $this->detectSqlInjection($header, $value, 'HEADER');
            }
        }
    }

    /**
     * Check request content for JSON/XML payloads
     */
    private function checkRequestContent(Request $request): void
    {
        $content = $request->getContent();
        
        if (empty($content)) {
            return;
        }

        // Check if content is JSON
        if ($request->isJson()) {
            $data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $this->checkParameters($data, 'JSON');
            } else {
                // If not valid JSON, check raw content
                $this->detectSqlInjection('raw_content', $content, 'RAW');
            }
        } else {
            // Check raw content for other content types
            $this->detectSqlInjection('raw_content', $content, 'RAW');
        }
    }

    /**
     * Detect SQL injection in a value
     */
    private function detectSqlInjection(string $key, string $value, string $source): void
    {
        // Convert to lowercase for case-insensitive matching
        $lowerValue = Str::lower($value);
        
        // Check for SQL keywords
        foreach ($this->sqlKeywords as $keyword) {
            if (str_contains($lowerValue, $keyword)) {
                // Check if it's a standalone word (not part of another word)
                if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/', $lowerValue)) {
                    $this->handleDetection($key, $value, $source, "SQL keyword: {$keyword}");
                }
            }
        }

        // Check regex patterns
        foreach ($this->sqlPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                $this->handleDetection($key, $value, $source, "Pattern match: {$pattern}");
            }
        }

        // Check for suspicious character sequences
        if ($this->hasSuspiciousSequence($value)) {
            $this->handleDetection($key, $value, $source, 'Suspicious character sequence');
        }

        // Check for encoded attacks
        $decoded = $this->checkEncodedAttacks($value);
        if ($decoded !== $value) {
            $this->detectSqlInjection($key, $decoded, "{$source}_DECODED");
        }
    }

    /**
     * Check for suspicious character sequences
     */
    private function hasSuspiciousSequence(string $value): bool
    {
        $suspiciousPatterns = [
            // Multiple quotes
            '/\'\'\'\'/',
            '/\"\"\"\"/',
            
            // Quote-escape patterns
            '/\\\'\s*or\s*\'/i',
            '/\\\"\s*or\s*\"/i',
            
            // Comment patterns
            '/\/\*.*\*\//s',
            '/--\s+/',
            
            // Semicolon patterns
            '/;\s*(select|insert|update|delete|drop)/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for encoded/obfuscated attacks
     */
    private function checkEncodedAttacks(string $value): string
    {
        // URL decode
        $decoded = urldecode($value);
        
        // HTML entity decode
        $decoded = html_entity_decode($decoded, ENT_QUOTES, 'UTF-8');
        
        // Base64 decode if it looks like base64
        if (preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $value) && strlen($value) % 4 == 0) {
            $base64Decoded = base64_decode($value, true);
            if ($base64Decoded !== false) {
                $decoded .= ' ' . $base64Decoded;
            }
        }

        // Hex decode
        if (preg_match_all('/0x([0-9a-f]+)/i', $value, $matches)) {
            foreach ($matches[1] as $hex) {
                $hexDecoded = hex2bin($hex);
                if ($hexDecoded !== false) {
                    $decoded .= ' ' . $hexDecoded;
                }
            }
        }

        return $decoded;
    }

    /**
     * Handle SQL injection detection
     */
    private function handleDetection(string $key, string $value, string $source, string $reason): void
    {
        // Log the attempt
        Log::warning('SQL injection attempt detected', [
            'key' => $key,
            'value' => substr($value, 0, 100), // Log only first 100 chars
            'source' => $source,
            'reason' => $reason,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now()->toISOString(),
            'url' => request()->fullUrl(),
        ]);

        // Throw validation exception
        throw new \Illuminate\Validation\ValidationException(
            validator([], []),
            response()->json([
                'error' => 'security_violation',
                'message' => 'Invalid input detected',
                'code' => 'SQL_INJECTION_ATTEMPT',
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID', 'N/A')
            ], 422)
        );
    }

    /**
     * Determine if middleware should skip this request
     */
    private function shouldSkip(Request $request): bool
    {
        // Skip for certain routes (e.g., health checks)
        $skipRoutes = [
            'system.health',
            'system.metrics',
            'status.*',
        ];

        $routeName = $request->route()->getName();
        
        foreach ($skipRoutes as $pattern) {
            if (str_ends_with($pattern, '.*')) {
                $prefix = rtrim($pattern, '.*');
                if (str_starts_with($routeName, $prefix)) {
                    return true;
                }
            } elseif ($routeName === $pattern) {
                return true;
            }
        }

        return false;
    }
}
