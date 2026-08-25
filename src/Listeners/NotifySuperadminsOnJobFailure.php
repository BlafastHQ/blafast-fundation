<?php

declare(strict_types=1);

namespace Blafast\Foundation\Listeners;

use Blafast\Foundation\Events\JobFailed;
use Blafast\Foundation\Notifications\JobFailedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Listener to notify superadmins when a job fails.
 *
 * Sends email and database notifications to all users with the
 * Superadmin role when a job fails after all retry attempts.
 */
class NotifySuperadminsOnJobFailure
{
    /**
     * Handle the event.
     */
    public function handle(JobFailed $event): void
    {
        // Get all Superadmins
        $superadmins = $this->getSuperadmins();

        if ($superadmins->isEmpty()) {
            return;
        }

        // Extract job details
        $jobClass = get_class($event->job);
        $errorMessage = $event->exception->getMessage();

        // Get organization ID from job if available — via the public accessor
        // (task 21): the property is protected, so the old direct read threw the
        // moment the superadmin lookup started matching.
        $organizationId = method_exists($event->job, 'organizationId')
            ? $event->job->organizationId()
            : null;

        // Send notification to all superadmins
        Notification::send(
            $superadmins,
            new JobFailedNotification(
                $jobClass,
                $errorMessage,
                $organizationId
            )
        );
    }

    /**
     * Get all users with Superadmin role.
     *
     * @return Collection<int, \stdClass>
     */
    protected function getSuperadmins()
    {
        // Task 21 (H19): the old raw query filtered model_type = 'App\\Models\\User'
        // while the package maps the alias 'user' and spatie stores
        // getMorphClass() — zero rows, so the advertised safety net was silently
        // dead (and stdClass rows would have fataled in Notification::send()).
        // Query REAL Notifiable models through the configured user class instead.
        $userModel = config('auth.providers.users.model');

        if (! is_string($userModel) || ! class_exists($userModel) || ! method_exists($userModel, 'scopeRole')) {
            return collect();
        }

        // Superadmin is a GLOBAL (null-team) role; the worker context was cleared
        // by the job middleware, so the team id is null here.
        return $userModel::role('Superadmin')->get();
    }
}
