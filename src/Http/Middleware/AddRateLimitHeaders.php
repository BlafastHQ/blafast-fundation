<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddRateLimitHeaders
{
    /**
     * Handle an incoming request and add rate limit headers to the response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Check if rate limit information is available in the request attributes
        if ($request->attributes->has('throttle_limit')) {
            $response->headers->set(
                'X-RateLimit-Limit',
                (string) $request->attributes->get('throttle_limit')
            );
        }

        if ($request->attributes->has('throttle_remaining')) {
            $response->headers->set(
                'X-RateLimit-Remaining',
                (string) $request->attributes->get('throttle_remaining')
            );
        }

        if ($request->attributes->has('throttle_reset')) {
            $response->headers->set(
                'X-RateLimit-Reset',
                (string) $request->attributes->get('throttle_reset')
            );
        }

        // Laravel's throttle middleware automatically adds these headers as:
        // X-RateLimit-Limit, X-RateLimit-Remaining, and Retry-After
        // So we don't need to do anything else if they're already present

        return $response;
    }
}
