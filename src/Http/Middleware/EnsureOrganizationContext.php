<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Middleware;

use Blafast\Foundation\Services\OrganizationContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureOrganizationContext Middleware
 *
 * Ensures that an organization context has been set (not global context).
 * This middleware should be applied to routes that REQUIRE an organization context.
 */
class EnsureOrganizationContext
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(OrganizationContext::class);

        // Check if we have an organization context (not global context)
        if (! $context->hasContext() && ! $context->isGlobalContext()) {
            return $this->organizationRequiredResponse();
        }

        // If we're in global context but this route requires organization context, deny
        if ($context->isGlobalContext()) {
            return $this->organizationRequiredResponse();
        }

        return $next($request);
    }

    /**
     * Return a JSON response for missing organization context.
     */
    private function organizationRequiredResponse(): JsonResponse
    {
        return response()->json([
            'errors' => [[
                'status' => '400',
                'code' => 'ORGANIZATION_REQUIRED',
                'title' => 'Organization Context Required',
                'detail' => 'This endpoint requires an organization context. Please provide X-Organization-Id header.',
            ]],
        ], 400);
    }
}
