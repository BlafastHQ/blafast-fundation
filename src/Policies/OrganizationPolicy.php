<?php

declare(strict_types=1);

namespace Blafast\Foundation\Policies;

use Blafast\Foundation\Models\Organization;
use Illuminate\Contracts\Auth\Authenticatable;

class OrganizationPolicy
{
    /**
     * Determine whether the user can view any organizations.
     */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('list_organization');
    }

    /**
     * Determine whether the user can view the organization.
     */
    public function view(Authenticatable $user, Organization $organization): bool
    {
        return $user->can('view_organization');
    }

    /**
     * Determine whether the user can create organizations.
     */
    public function create(Authenticatable $user): bool
    {
        return $user->can('create_organization');
    }

    /**
     * Determine whether the user can update the organization.
     */
    public function update(Authenticatable $user, Organization $organization): bool
    {
        return $user->can('update_organization');
    }

    /**
     * Determine whether the user can delete the organization.
     */
    public function delete(Authenticatable $user, Organization $organization): bool
    {
        return $user->can('delete_organization');
    }

    /**
     * Determine whether the user can manage the current organization's settings
     * (task 9 / H5 — the controller used to authorize a `manage` ability that
     * existed nowhere, denying everyone by default). Class-based check: the
     * target organization is the resolved context. Superadmins pass explicitly —
     * under an org context their global grants are invisible to the team-scoped
     * permission lookup.
     */
    public function manageSettings(Authenticatable $user): bool
    {
        if (method_exists($user, 'isSuperadmin') && $user->isSuperadmin()) {
            return true;
        }

        return $user->can('update_organization');
    }
}
