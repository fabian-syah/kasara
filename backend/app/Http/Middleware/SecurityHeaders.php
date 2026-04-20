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
        /** @var Response $response */
        $response = $next($request);

        // Security Headers for Best Practices score (100)
        
        // 1. HSTS (Strict-Transport-Security)
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        
        // 2. Clickjacking (X-Frame-Options & CSP frame-ancestors)
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // 3. Content-Type Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // 4. Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // 5. Origin Isolation (COOP & COEP)
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'require-corp');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // 6. Strict Content-Security-Policy (CSP)
        // Note: Using 'unsafe-inline' for style-src and script-src because of Vite/Vue requirements
        // but adding frame-ancestors and object-src 'none' for security.
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://api.stokps.com; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
               "font-src 'self' https://fonts.gstatic.com; " .
               "img-src 'self' data: https://ui-avatars.com https://api.stokps.com; " .
               "connect-src 'self' https://api.stokps.com https://www.emsifa.com; " .
               "object-src 'none'; " .
               "frame-ancestors 'none'; " .
               "base-uri 'self'; " .
               "form-action 'self'; " .
               "upgrade-insecure-requests";
        
        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }
}
