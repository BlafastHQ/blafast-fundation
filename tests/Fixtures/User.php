<?php

declare(strict_types=1);

namespace Blafast\Foundation\Tests\Fixtures;

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable;
    use Authorizable;
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use HasUuids;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * Check if user has permission in specific organization context.
     */
    public function hasOrganizationPermission(string $permission, Organization|string|null $organization = null): bool
    {
        if ($organization === null) {
            $context = app(OrganizationContext::class);
            $organization = $context->organization();
        }

        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $this->hasPermissionTo($permission, 'api', $organizationId);
    }

    /**
     * Check if user has role in specific organization context.
     */
    public function hasOrganizationRole(string $role, Organization|string|null $organization = null): bool
    {
        if ($organization === null) {
            $context = app(OrganizationContext::class);
            $organization = $context->organization();
        }

        $organizationId = $organization instanceof Organization ? $organization->id : $organization;

        return $this->hasRole($role, 'api', $organizationId);
    }

    /**
     * Check if user is a Superadmin.
     */
    public function isSuperadmin(): bool
    {
        return $this->hasRole('Superadmin', 'api');
    }
}
