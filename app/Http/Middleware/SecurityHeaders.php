<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Minimal security headers. CSP and other restrictive policies are intentionally
 * disabled so the application can load resources from any origin (WooCommerce,
 * external CDNs, WhatsApp, etc.) without blocking. Keep only headers that do
 * not interfere with cross-origin loading.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $isPublicPage = $request->routeIs('home') || $request->routeIs('pages.show') || $request->routeIs('sitemap');
        $existingCacheControl = (string) $response->headers->get('Cache-Control', '');
        if ($isPublicPage && ($existingCacheControl === '' || $existingCacheControl === 'no-cache, private')) {
            $response->headers->set('Cache-Control', 'public, max-age=300, must-revalidate');
        }

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set('Content-Security-Policy', "default-src * 'unsafe-inline' 'unsafe-eval' data: blob:; img-src * data: blob:; connect-src * ws: wss:; frame-src *; font-src * data:; media-src * data: blob:; style-src * 'unsafe-inline';");

        return $response;
    }
}
