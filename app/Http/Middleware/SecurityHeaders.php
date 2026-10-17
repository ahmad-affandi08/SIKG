<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan (docs/rules/security-privacy.md bagian 4): CSP ketat, HSTS, anti-framing, anti-sniffing.
 * Aset dibundel Vite dan disajikan dari asal yang sama; tanpa skrip atau font CDN.
 */
final class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('Content-Security-Policy', $this->contentSecurityPolicy());
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function contentSecurityPolicy(): string
    {
        // Server pengembangan Vite (berkas `public/hot`) berjalan di asal lain dan memakai WebSocket HMR.
        $dev = is_file(public_path('hot')) ? ' http://localhost:5173 http://127.0.0.1:5173 http://[::1]:5173' : '';
        $devWs = $dev !== '' ? ' ws://localhost:5173 ws://127.0.0.1:5173 ws://[::1]:5173' : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self'".$dev,
            "style-src 'self'".$dev,
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self'".$dev,
            "connect-src 'self'".$dev.$devWs,
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
