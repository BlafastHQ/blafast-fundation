<?php

declare(strict_types=1);

namespace Blafast\Foundation\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap rate limiting services.
     */
    public function boot(): void
    {
        $this->configureAuthRateLimiter();
        $this->configureApiRateLimiter();
        $this->configureDeferredRateLimiter();
    }

    /**
     * Configure rate limiter for authentication endpoints.
     */
    protected function configureAuthRateLimiter(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            $maxAttempts = config('blafast-fundation.api.rate_limiting.auth.max_attempts', 60);

            return Limit::perMinute($maxAttempts)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'errors' => [[
                            'status' => '429',
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'title' => 'Rate Limit Exceeded',
                            'detail' => 'Too many authentication attempts. Please try again later.',
                            'meta' => [
                                'retry_after' => $headers['Retry-After'] ?? 60,
                            ],
                        ]],
                    ], 429, $headers);
                });
        });
    }

    /**
     * Configure rate limiter for standard API endpoints.
     */
    protected function configureApiRateLimiter(): void
    {
        RateLimiter::for('api', function (Request $request) {
            // Check if superadmins should be exempt
            if ($this->shouldExemptFromRateLimit($request)) {
                return Limit::none();
            }

            $maxAttempts = config('blafast-fundation.api.rate_limiting.api.max_attempts', 300);

            return Limit::perMinute($maxAttempts)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'errors' => [[
                            'status' => '429',
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'title' => 'Rate Limit Exceeded',
                            'detail' => 'Too many requests. Please try again later.',
                            'meta' => [
                                'retry_after' => $headers['Retry-After'] ?? 60,
                            ],
                        ]],
                    ], 429, $headers);
                });
        });
    }

    /**
     * Configure rate limiter for deferred request endpoints.
     */
    protected function configureDeferredRateLimiter(): void
    {
        RateLimiter::for('deferred', function (Request $request) {
            // Check if superadmins should be exempt
            if ($this->shouldExemptFromRateLimit($request)) {
                return Limit::none();
            }

            return Limit::perMinute(300)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'errors' => [[
                            'status' => '429',
                            'code' => 'RATE_LIMIT_EXCEEDED',
                            'title' => 'Rate Limit Exceeded',
                            'detail' => 'Too many deferred requests. Please try again later.',
                            'meta' => [
                                'retry_after' => $headers['Retry-After'] ?? 60,
                            ],
                        ]],
                    ], 429, $headers);
                });
        });
    }

    /**
     * Determine if the request should be exempt from rate limiting.
     */
    protected function shouldExemptFromRateLimit(Request $request): bool
    {
        // Check config setting
        if (! config('blafast-fundation.api.rate_limiting.exempt_superadmins', true)) {
            return false;
        }

        // Check if user has Superadmin role
        $user = $request->user();

        if (! $user) {
            return false;
        }

        // Check if user has the hasRole method (from Spatie Permission package)
        if (! method_exists($user, 'hasRole')) {
            return false;
        }

        return $user->hasRole('Superadmin');
    }
}
