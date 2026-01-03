<?php

declare(strict_types=1);

namespace Blafast\Foundation\Traits;

use Blafast\Foundation\Models\Activity;
use Blafast\Foundation\Services\OrganizationContext;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Trait for logging model activity with organization context.
 *
 * This trait extends Spatie's LogsActivity trait to add organization
 * awareness, ensuring activities are properly scoped to organizations.
 *
 * Usage:
 * ```
 * class Invoice extends Model
 * {
 *     use LogsOrganizationActivity;
 * }
 * ```
 */
trait LogsOrganizationActivity
{
    use LogsActivity;

    /**
     * Get the options for logging activity.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->getLoggableAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $eventName) => $eventName);
    }

    /**
     * Get attributes to log.
     *
     * Override this method in your model to customize which attributes are logged.
     *
     * @return array<string>
     */
    protected function getLoggableAttributes(): array
    {
        return $this->fillable;
    }

    /**
     * Customize the activity before it's saved.
     *
     * This method ensures the organization_id is set from the current context.
     */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $context = app(OrganizationContext::class);

        if ($context->hasContext()) {
            $activity->organization_id = $context->id();
        }
    }
}
