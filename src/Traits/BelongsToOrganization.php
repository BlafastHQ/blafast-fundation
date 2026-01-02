<?php

declare(strict_types=1);

namespace Blafast\Foundation\Traits;

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Scopes\OrganizationScope;
use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BelongsToOrganization Trait
 *
 * Applies automatic multi-tenant data isolation to models via the OrganizationScope.
 * Also automatically assigns organization_id when creating new records.
 *
 * @property string $organization_id
 * @property-read Organization $organization
 */
trait BelongsToOrganization
{
    /**
     * Boot the BelongsToOrganization trait for a model.
     *
     * @return void
     */
    public static function bootBelongsToOrganization(): void
    {
        // Add the global scope to automatically filter queries
        static::addGlobalScope(new OrganizationScope());

        // Automatically assign organization_id when creating new records
        static::creating(function (Model $model) {
            if (empty($model->organization_id)) {
                $context = app(OrganizationContext::class);

                if ($context->hasContext()) {
                    $model->organization_id = $context->id();
                }
            }
        });
    }

    /**
     * Get the organization that owns this model.
     *
     * @return BelongsTo<Organization, Model>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Query the model without the organization scope.
     * This is useful for administrative operations that need to see all records.
     *
     * @return Builder<static>
     */
    public static function withoutOrganizationScope(): Builder
    {
        return static::withoutGlobalScope(OrganizationScope::class);
    }

    /**
     * Query the model for a specific organization.
     * This bypasses the context and allows querying a specific organization's data.
     *
     * @param string $organizationId
     * @return Builder<static>
     */
    public static function forOrganization(string $organizationId): Builder
    {
        return static::withoutOrganizationScope()
            ->where('organization_id', $organizationId);
    }

    /**
     * Scope a query to only include models for a specific organization.
     *
     * @param Builder<static> $query
     * @param string $organizationId
     * @return Builder<static>
     */
    public function scopeForOrganization(Builder $query, string $organizationId): Builder
    {
        return $query->where($this->getTable().'.organization_id', $organizationId);
    }
}
