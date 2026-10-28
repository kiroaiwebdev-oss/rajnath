<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $h = $response->headers;
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(self), microphone=(self), geolocation=(), payment=(self)');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        $dev = app()->environment('local') ? ' http://localhost:5173 ws://localhost:5173' : '';
        $h->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-$nonce' https://checkout.razorpay.com$dev",
            "style-src 'self' 'unsafe-inline'$dev",
            "img-src 'self' data: blob:",
            "font-src 'self'",
            "connect-src 'self' https://api.razorpay.com wss:$dev",
            'frame-src https://api.razorpay.com https://checkout.razorpay.com',
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ]));
        if ($request->is('admin/*', 'admin', 'staff/*', 'account/*')) {
            $h->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
