<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use Blafast\Foundation\Scopes\ActivityOrganizationScope;
use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/**
 * Custom Activity model with organization support.
 *
 * Extends Spatie's Activity model to add:
 * - UUID primary keys
 * - Organization scoping for multi-tenancy
 * - Automatic organization_id assignment
 *
 * @property string $id
 * @property string|null $organization_id
 * @property Organization|null $organization
 */
class Activity extends BaseActivity
{
    use HasUuids;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        parent::booted();

        // Add organization scope for data isolation
        static::addGlobalScope(new ActivityOrganizationScope);

        // Set organization_id when creating
        static::creating(function (Activity $activity) {
            if (empty($activity->organization_id)) {
                $context = app(OrganizationContext::class);
                if ($context->hasContext()) {
                    $activity->organization_id = $context->id();
                }
            }
        });
    }

    /**
     * Get the organization that owns the activity.
     *
     * @return BelongsTo<Organization, Activity>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Query without organization scope.
     *
     * @return Builder<Activity>
     */
    public static function withoutOrganizationScope(): Builder
    {
        return static::withoutGlobalScope(ActivityOrganizationScope::class);
    }
}
