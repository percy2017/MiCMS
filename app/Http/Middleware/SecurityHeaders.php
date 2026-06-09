<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline security headers to every HTTP response.
 * - X-Content-Type-Options: prevent MIME sniffing
 * - X-Frame-Options: prevent clickjacking
 * - Referrer-Policy: do not leak full URL to third parties
 * - Permissions-Policy: disable unused browser features
 * - Strict-Transport-Security: enforce HTTPS (only in production)
 * - Content-Security-Policy: allow own assets, Puck CSS, images; block unsafe inline scripts
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
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        $isPublicPage = $request->routeIs('home') || $request->routeIs('pages.show') || $request->routeIs('sitemap');
        $existingCacheControl = (string) $response->headers->get('Cache-Control', '');
        if ($isPublicPage && ($existingCacheControl === '' || $existingCacheControl === 'no-cache, private')) {
            $response->headers->set('Cache-Control', 'public, max-age=300, must-revalidate');
        }

        if (app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $response->headers->has('Content-Security-Policy')) {
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline' https://rsms.me",
                "style-src-elem 'self' 'unsafe-inline' https://rsms.me",
                "img-src 'self' data: blob:",
                "font-src 'self' data: https://rsms.me",
                "connect-src 'self' ws: wss:",
                "media-src 'self'",
                "frame-src 'self' https://www.youtube.com https://player.vimeo.com",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ]);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
