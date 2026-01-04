<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Middleware;

use Blafast\Foundation\Enums\DeferredRequestStatus;
use Blafast\Foundation\Jobs\ProcessDeferredApiRequest;
use Blafast\Foundation\Models\DeferredApiRequest;
use Blafast\Foundation\Models\DeferredEndpointConfig;
use Blafast\Foundation\Services\OrganizationContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DeferredRequestMiddleware
 *
 * Intercepts API requests and defers them to background jobs when configured.
 */
class DeferredRequestMiddleware
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private OrganizationContext $orgContext
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if already a deferred execution (prevent infinite loop)
        if ($request->header('X-Deferred-Execution') === 'true') {
            return $next($request);
        }

        // Skip if no authenticated user
        if (!$request->user()) {
            return $next($request);
        }

        // Find matching configuration
        $config = $this->findMatchingConfig($request);

        if (!$config) {
            return $next($request);
        }

        // Check if request should be deferred
        if (!$this->shouldDefer($request, $config)) {
            return $next($request);
        }

        // Create and dispatch deferred request
        return $this->createDeferredRequest($request, $config);
    }

    /**
     * Find matching endpoint configuration.
     */
    protected function findMatchingConfig(Request $request): ?DeferredEndpointConfig
    {
        // Check database configs first
        $dbConfig = DeferredEndpointConfig::query()
            ->where('is_active', true)
            ->where('http_method', $request->method())
            ->where(function ($q) {
                $orgId = $this->orgContext->id();
                $q->whereNull('organization_id')
                    ->orWhere('organization_id', $orgId);
            })
            ->get()
            ->first(fn ($c) => $this->matchesPattern($request->path(), $c->endpoint_pattern));

        if ($dbConfig) {
            return $dbConfig;
        }

        // Fallback to file config (if implemented in config/blafast.php)
        return $this->findFileConfig($request);
    }

    /**
     * Check if path matches endpoint pattern.
     */
    protected function matchesPattern(string $path, string $pattern): bool
    {
        // Convert pattern to regex (simple version - can be enhanced)
        $regex = str_replace(['*', '/'], ['[^/]+', '\/'], $pattern);

        return (bool) preg_match("/^{$regex}$/", $path);
    }

    /**
     * Find configuration from file-based config.
     */
    protected function findFileConfig(Request $request): ?DeferredEndpointConfig
    {
        // TODO: Implement file-based config fallback
        // This would read from config('blafast.deferred_endpoints')
        return null;
    }

    /**
     * Determine if request should be deferred.
     */
    protected function shouldDefer(Request $request, DeferredEndpointConfig $config): bool
    {
        // If endpoint forces deferral, always defer
        if ($config->force_deferred) {
            return true;
        }

        // Otherwise, check for explicit defer header
        return $request->header('X-Blafast-Defer') === 'true';
    }

    /**
     * Create deferred request and return 202 response.
     */
    protected function createDeferredRequest(Request $request, DeferredEndpointConfig $config): JsonResponse
    {
        $deferred = DeferredApiRequest::create([
            'organization_id' => $this->orgContext->id(),
            'user_id' => $request->user()->id,
            'http_method' => $request->method(),
            'endpoint' => $request->path(),
            'payload' => $request->isMethod('GET') ? null : $request->all(),
            'query_params' => $request->query(),
            'headers' => $this->filterHeaders($request->headers->all()),
            'status' => DeferredRequestStatus::Pending,
            'priority' => $config->priority ?? 'default',
            'max_attempts' => 3,
            'expires_at' => now()->addSeconds($config->result_ttl ?? 3600),
        ]);

        // Dispatch background job
        ProcessDeferredApiRequest::dispatch($deferred);

        return response()->json([
            'data' => [
                'type' => 'deferred-request',
                'id' => $deferred->id,
                'attributes' => [
                    'status' => $deferred->status->value,
                    'endpoint' => $deferred->endpoint,
                    'http_method' => $deferred->http_method,
                    'created_at' => $deferred->created_at->toIso8601String(),
                    'expires_at' => $deferred->expires_at->toIso8601String(),
                ],
                'links' => [
                    'self' => route('deferred.show', ['id' => $deferred->id]),
                    'poll' => route('deferred.show', ['id' => $deferred->id]),
                ],
            ],
        ], 202);
    }

    /**
     * Filter headers to only include safe ones.
     */
    protected function filterHeaders(array $headers): array
    {
        $allowed = ['accept', 'content-type', 'x-organization-id', 'accept-language'];

        return array_intersect_key(
            $headers,
            array_flip(array_map('strtolower', $allowed))
        );
    }
}
