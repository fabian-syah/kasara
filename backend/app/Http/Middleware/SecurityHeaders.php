<?php
 
namespace App\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
 
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        // Generate a cryptographic nonce for CSP script/style allowlisting
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);

        /** @var Response $response */
        $response = $next($request);
 
        // Security Headers for Best Practices score (100)
        
        // 1. HSTS (Strict-Transport-Security)
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        
        // 2. Clickjacking (X-Frame-Options)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        
        // 3. Content-Type Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // 4. Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // 5. Origin Isolation (COOP, COEP, CORP)
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'credentialless'); 
        $response->headers->set('Cross-Origin-Resource-Policy', 'cross-origin');
 
        // 6. Permissions Policy
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), payment=()');
 
        // 7. Nonce-based Content-Security-Policy (CSP)
        // Removes 'unsafe-inline' and 'unsafe-eval' from script-src, replaced with nonce.
        // Keeps 'unsafe-inline' for style-src because Tailwind/Vue may inject inline styles
        // for transitions and dynamic bindings.
        $appUrl = config('app.url');

        $csp = "default-src 'self'; " .
            "script-src 'self' 'nonce-{$nonce}' 'wasm-unsafe-eval' {$appUrl}; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
            "font-src 'self' https://fonts.gstatic.com data:; " .
            "img-src 'self' data: blob: https://ui-avatars.com {$appUrl}; " .
            "connect-src 'self' {$appUrl} https://fonts.googleapis.com https://fonts.gstatic.com https://www.emsifa.com wss:; " .
            "worker-src 'self' blob:; " .
            "object-src 'none'; " .
            "frame-ancestors 'self'; " .
            "base-uri 'self'; " .
            "form-action 'self'; " .
            "upgrade-insecure-requests";

        // Deploy as Report-Only first to monitor violations without breaking production.
        // Once verified safe, switch to enforcing: Content-Security-Policy
        $response->headers->set('Content-Security-Policy-Report-Only', $csp);
 
        return $response;
    }
}
