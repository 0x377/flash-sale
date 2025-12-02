<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XssProtection
{
    /**
     * XSS attack patterns
     */
    private array $xssPatterns = [
        // Script tags
        '/<script\b[^>]*>(.*?)<\/script>/is',
        '/<script>/i',
        
        // JavaScript event handlers
        '/on\w+\s*=\s*["\'][^"\']*["\']/i',
        '/on\w+\s*=\s*[^"\'][^>]*[^"\']/i',
        
        // JavaScript protocols
        '/javascript\s*:/i',
        '/data\s*:/i',
        '/vbscript\s*:/i',
        
        // Iframe tags
        '/<iframe\b[^>]*>(.*?)<\/iframe>/is',
        
        // Object/embed tags
        '/<object\b[^>]*>(.*?)<\/object>/is',
        '/<embed\b[^>]*>(.*?)<\/embed>/is',
        '/<applet\b[^>]*>(.*?)<\/applet>/is',
        
        // Style tags with expressions
        '/<style\b[^>]*>(.*?)<\/style>/is',
        '/expression\s*\(/i',
        
        // Meta refresh
        '/<meta[^>]*http-equiv\s*=\s*["\']?refresh["\']?[^>]*>/i',
        
        // Base tags (could change base URL for relative links)
        '/<base\b[^>]*>/i',
        
        // Form tags with action
        '/<form\b[^>]*>/i',
        
        // Input tags with malicious attributes
        '/<input[^>]*type\s*=\s*["\']?hidden["\']?[^>]*>/i',
        
        // SVG with scripts
        '/<svg\b[^>]*>(.*?)<\/svg>/is',
        '/<svg>/i',
        
        // Marquees (can be used for phishing)
        '/<marquee\b[^>]*>(.*?)<\/marquee>/is',
        
        // Link tags with malicious URLs
        '/<link[^>]*href\s*=\s*["\'][^"\']*javascript:/i',
        
        // Img tags with malicious attributes
        '/<img[^>]*src\s*=\s*["\'][^"\']*javascript:/i',
        '/<img[^>]*onerror\s*=/i',
        
        // Body tags with events
        '/<body\b[^>]*onload\s*=/i',
        
        // Div tags with events
        '/<div[^>]*onclick\s*=/i',
        
        // Anchor tags with malicious href
        '/<a\b[^>]*href\s*=\s*["\'][^"\']*javascript:/i',
        
        // CSS import/url with javascript
        '/@import[^;]*;/i',
        '/url\s*\(\s*["\']?javascript:/i',
    ];

    /**
     * Dangerous HTML attributes
     */
    private array $dangerousAttributes = [
        'onabort', 'onactivate', 'onafterprint', 'onafterupdate', 'onbeforeactivate',
        'onbeforecopy', 'onbeforecut', 'onbeforedeactivate', 'onbeforeeditfocus',
        'onbeforepaste', 'onbeforeprint', 'onbeforeunload', 'onbeforeupdate',
        'onblur', 'onbounce', 'oncellchange', 'onchange', 'onclick', 'oncontextmenu',
        'oncontrolselect', 'oncopy', 'oncut', 'ondataavailable', 'ondatasetchanged',
        'ondatasetcomplete', 'ondblclick', 'ondeactivate', 'ondrag', 'ondragend',
        'ondragenter', 'ondragleave', 'ondragover', 'ondragstart', 'ondrop',
        'onerror', 'onerrorupdate', 'onfilterchange', 'onfinish', 'onfocus',
        'onfocusin', 'onfocusout', 'onhelp', 'onkeydown', 'onkeypress', 'onkeyup',
        'onlayoutcomplete', 'onload', 'onlosecapture', 'onmousedown', 'onmouseenter',
        'onmouseleave', 'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup',
        'onmousewheel', 'onmove', 'onmoveend', 'onmovestart', 'onpaste', 'onpropertychange',
        'onreadystatechange', 'onreset', 'onresize', 'onresizeend', 'onresizestart',
        'onrowenter', 'onrowexit', 'onrowsdelete', 'onrowsinserted', 'onscroll',
        'onselect', 'onselectionchange', 'onselectstart', 'onstart', 'onstop',
        'onsubmit', 'onunload',
        
        // Style attributes that can execute code
        'style',
        
        // Attributes that can load external resources
        'src', 'href', 'action', 'formaction', 'poster', 'data', 'cite',
        
        // Attributes that can execute code through URI
        'background', 'dynsrc', 'lowsrc',
    ];

    /**
     * Handle the incoming request
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip for certain routes
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // Check all input data
        $this->sanitizeInputs($request);

        // Process response
        $response = $next($request);

        // Add XSS protection headers
        return $this->addSecurityHeaders($response);
    }

    /**
     * Sanitize all input data
     */
    private function sanitizeInputs(Request $request): void
    {
        // Check GET parameters
        $this->checkForXss($request->query(), 'GET');
        
        // Check POST parameters
        $this->checkForXss($request->post(), 'POST');
        
        // Check JSON payload
        if ($request->isJson()) {
            $data = $request->json()->all();
            $this->checkForXss($data, 'JSON');
        }
        
        // Check route parameters
        $routeParams = $request->route()->parameters();
        $this->checkForXss($routeParams, 'ROUTE');
        
        // Check headers that might contain XSS
        $this->checkHeaders($request);
    }

    /**
     * Check data for XSS patterns
     */
    private function checkForXss(array $data, string $source, string $path = ''): void
    {
        foreach ($data as $key => $value) {
            $currentPath = $path ? "{$path}.{$key}" : $key;
            
            if (is_array($value)) {
                $this->checkForXss($value, $source, $currentPath);
                continue;
            }

            if (is_string($value)) {
                $this->detectXss($currentPath, $value, $source);
            }
        }
    }

    /**
     * Check headers for XSS
     */
    private function checkHeaders(Request $request): void
    {
        $headersToCheck = [
            'User-Agent',
            'Referer',
            'Origin',
            'X-Forwarded-For',
            'X-Real-IP',
        ];

        foreach ($headersToCheck as $header) {
            if ($value = $request->header($header)) {
                $this->detectXss($header, $value, 'HEADER');
            }
        }
    }

    /**
     * Detect XSS in a value
     */
    private function detectXss(string $key, string $value, string $source): void
    {
        // Skip empty values
        if (empty(trim($value))) {
            return;
        }

        // Check for encoded attacks first
        $decodedValue = $this->decodeAttack($value);
        
        // Check original value
        if ($this->hasXssPattern($value)) {
            $this->handleXssDetection($key, $value, $source, 'XSS pattern detected');
        }
        
        // Check decoded value
        if ($decodedValue !== $value && $this->hasXssPattern($decodedValue)) {
            $this->handleXssDetection($key, $value, $source, 'Encoded XSS pattern detected');
        }
        
        // Check for dangerous HTML attributes
        if ($this->hasDangerousAttributes($value)) {
            $this->handleXssDetection($key, $value, $source, 'Dangerous HTML attribute detected');
        }
        
        // Check for suspicious HTML entities
        if ($this->hasSuspiciousEntities($value)) {
            $this->handleXssDetection($key, $value, $source, 'Suspicious HTML entities detected');
        }
    }

    /**
     * Decode potential attack vectors
     */
    private function decodeAttack(string $value): string
    {
        $decoded = $value;
        
        // URL decode
        $decoded = urldecode($decoded);
        
        // HTML entity decode
        $decoded = html_entity_decode($decoded, ENT_QUOTES, 'UTF-8');
        
        // Base64 decode if it looks like base64
        if (preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $value) && strlen($value) % 4 == 0) {
            $base64 = base64_decode($value, true);
            if ($base64 !== false) {
                $decoded .= ' ' . $base64;
            }
        }
        
        // Hex decode
        if (preg_match('/\\\x[0-9a-f]{2}/i', $value)) {
            $decoded .= ' ' . preg_replace_callback('/\\\x([0-9a-f]{2})/i', 
                fn($matches) => chr(hexdec($matches[1])), 
                $value
            );
        }
        
        // Unicode decode
        if (preg_match('/\\\u[0-9a-f]{4}/i', $value)) {
            $decoded .= ' ' . json_decode('"' . str_replace('"', '\"', $value) . '"');
        }
        
        return $decoded;
    }

    /**
     * Check for XSS patterns
     */
    private function hasXssPattern(string $value): bool
    {
        foreach ($this->xssPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check for dangerous HTML attributes
     */
    private function hasDangerousAttributes(string $value): bool
    {
        foreach ($this->dangerousAttributes as $attribute) {
            $pattern = '/\b' . preg_quote($attribute, '/') . '\s*=\s*["\'][^"\']*["\']/i';
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check for suspicious HTML entities
     */
    private function hasSuspiciousEntities(string $value): bool
    {
        // Check for multiple encoded characters
        $encodedCount = substr_count($value, '&');
        $semicolonCount = substr_count($value, ';');
        
        if ($encodedCount > 5 && $semicolonCount > 5 && $encodedCount === $semicolonCount) {
            // This might be an encoded attack
            return true;
        }
        
        // Check for encoded script tags
        $encodedScripts = [
            '&lt;script',
            '&#60;script',
            '&#x3c;script',
            '%3Cscript',
        ];
        
        foreach ($encodedScripts as $encoded) {
            if (stripos($value, $encoded) !== false) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Handle XSS detection
     */
    private function handleXssDetection(string $key, string $value, string $source, string $reason): void
    {
        // Log the attempt
        Log::warning('XSS attempt detected', [
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
                'code' => 'XSS_ATTEMPT',
                'timestamp' => now()->toISOString(),
                'request_id' => request()->header('X-Request-ID', 'N/A')
            ], 422)
        );
    }

    /**
     * Add security headers to response
     */
    private function addSecurityHeaders($response)
    {
        // Add XSS protection headers
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Add Content Security Policy
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline'; " .
               "img-src 'self' data:; " .
               "font-src 'self'; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none'; " .
               "form-action 'self'; " .
               "base-uri 'self';";
        
        $response->headers->set('Content-Security-Policy', $csp);
        
        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        return $response;
    }

    /**
     * Determine if middleware should skip this request
     */
    private function shouldSkip(Request $request): bool
    {
        $skipRoutes = [
            'system.health',
            'system.metrics',
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
