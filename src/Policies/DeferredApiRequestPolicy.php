<?php

declare(strict_types=1);

namespace Blafast\Foundation\Policies;

use Blafast\Foundation\Models\DeferredApiRequest;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Policy for DeferredApiRequest model authorization.
 *
 * Controls access to deferred API requests. Users can only access
 * their own deferred requests unless they are Superadmins.
 */
class DeferredApiRequestPolicy
{
    /**
     * Determine whether the user can view any deferred requests.
     */
    public function viewAny(Authenticatable $user): bool
    {
        // All authenticated users can view their own deferred requests
        return true;
    }

    /**
     * Determine whether the user can view the deferred request.
     */
    public function view(Authenticatable $user, DeferredApiRequest $deferredRequest): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('Superadmin')) {
            return true;
        }

        // @phpstan-ignore property.notFound
        return $deferredRequest->user_id === $user->id;
    }

    /**
     * Determine whether the user can cancel the deferred request.
     */
    public function cancel(Authenticatable $user, DeferredApiRequest $deferredRequest): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('Superadmin')) {
            return true;
        }

        // @phpstan-ignore property.notFound
        return $deferredRequest->user_id === $user->id;
    }

    /**
     * Determine whether the user can retry the deferred request.
     */
    public function retry(Authenticatable $user, DeferredApiRequest $deferredRequest): bool
    {
        if (method_exists($user, 'hasRole') && $user->hasRole('Superadmin')) {
            return true;
        }

        // @phpstan-ignore property.notFound
        return $deferredRequest->user_id === $user->id;
    }
}
