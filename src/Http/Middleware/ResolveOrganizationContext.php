<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Middleware;

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\OrganizationContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ResolveOrganizationContext Middleware
 *
 * Resolves the organization context from the X-Organization-Id header,
 * validates user membership, and populates the OrganizationContext singleton.
 */
class ResolveOrganizationContext
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private OrganizationContext $context
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip if no authenticated user
        if (! $user) {
            return $next($request);
        }

        // Check if Superadmin without header = global context
        if ($this->shouldUseGlobalContext($request, $user)) {
            $this->context->setGlobalContext($user);

            return $next($request);
        }

        // Get organization ID from header or session fallback
        $organizationId = $this->resolveOrganizationId($request);

        if (! $organizationId) {
            return $this->missingOrganizationResponse();
        }

        // Validate and get organization
        $organization = $this->validateAndGetOrganization($user, $organizationId);

        if (! $organization) {
            return $this->accessDeniedResponse();
        }

        // Check if membership is active
        if (! $this->isMembershipActive($user, $organization)) {
            return $this->membershipInactiveResponse();
        }

        // Set organization context
        $this->context->set($organization, $user);

        // Store in session for SPA fallback
        $this->storeInSession($request, $organizationId);

        return $next($request);
    }

    /**
     * Determine if the request should use global context.
     * This is true when the user is a Superadmin and no X-Organization-Id header is provided.
     */
    private function shouldUseGlobalContext(Request $request, object $user): bool
    {
        // Check if user is Superadmin
        if (! method_exists($user, 'isSuperadmin') || ! $user->isSuperadmin()) {
            return false;
        }

        // Check if X-Organization-Id header is NOT provided
        return ! $request->hasHeader('X-Organization-Id');
    }

    /**
     * Resolve the organization ID from the request header or session.
     */
    private function resolveOrganizationId(Request $request): ?string
    {
        // First, try to get from X-Organization-Id header
        $organizationId = $request->header('X-Organization-Id');

        if ($organizationId) {
            return $organizationId;
        }

        // Fallback to session for SPA convenience
        return $request->session()->get('organization_id');
    }

    /**
     * Validate that the user belongs to the organization and get the organization.
     */
    private function validateAndGetOrganization(object $user, string $organizationId): ?Organization
    {
        // Get the organization
        $organization = Organization::find($organizationId);

        if (! $organization) {
            return null;
        }

        // Validate user belongs to organization
        if (! $organization->hasUser($user)) {
            return null;
        }

        return $organization;
    }

    /**
     * Check if the user's membership in the organization is active.
     */
    private function isMembershipActive(object $user, Organization $organization): bool
    {
        $userWithPivot = $organization->users()
            ->where('user_id', $user->id)
            ->first();

        if (! $userWithPivot) {
            return false;
        }

        /** @var \Blafast\Foundation\Models\OrganizationUser|null $pivot */
        $pivot = $userWithPivot->pivot;

        if (! $pivot) {
            return false;
        }

        return $pivot->isActive();
    }

    /**
     * Store the organization ID in the session for SPA fallback.
     */
    private function storeInSession(Request $request, string $organizationId): void
    {
        if ($request->hasSession()) {
            $request->session()->put('organization_id', $organizationId);
        }
    }

    /**
     * Return a JSON response for missing organization header.
     */
    private function missingOrganizationResponse(): JsonResponse
    {
        return response()->json([
            'errors' => [[
                'status' => '400',
                'code' => 'MISSING_ORGANIZATION',
                'title' => 'Organization Required',
                'detail' => 'The X-Organization-Id header is required for this request.',
            ]],
        ], 400);
    }

    /**
     * Return a JSON response for access denied.
     */
    private function accessDeniedResponse(): JsonResponse
    {
        return response()->json([
            'errors' => [[
                'status' => '403',
                'code' => 'ORGANIZATION_ACCESS_DENIED',
                'title' => 'Access Denied',
                'detail' => 'You do not have access to this organization.',
            ]],
        ], 403);
    }

    /**
     * Return a JSON response for inactive membership.
     */
    private function membershipInactiveResponse(): JsonResponse
    {
        return response()->json([
            'errors' => [[
                'status' => '403',
                'code' => 'MEMBERSHIP_INACTIVE',
                'title' => 'Membership Inactive',
                'detail' => 'Your membership in this organization is not active.',
            ]],
        ], 403);
    }
}
