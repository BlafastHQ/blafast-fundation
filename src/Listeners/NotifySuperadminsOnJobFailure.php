<?php

declare(strict_types=1);

namespace Blafast\Foundation\Listeners;

use Blafast\Foundation\Events\JobFailed;
use Blafast\Foundation\Notifications\JobFailedNotification;
use Illuminate\Support\Facades\DB;
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

        // Get organization ID from job if available
        $organizationId = null;
        if (property_exists($event->job, 'organizationId')) {
            $organizationId = $event->job->organizationId;
        }

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
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    protected function getSuperadmins()
    {
        // Query users who have the Superadmin role
        // This uses Spatie Permission package
        // Note: We use DB facade instead of User model since this is a package
        // and we don't have direct access to the app's User model
        return DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
            })
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('roles.name', '=', 'Superadmin')
            ->select('users.*')
            ->distinct()
            ->get();
    }
}
