<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Scheduler status API endpoint.
 *
 * Provides information about the scheduler health and status
 * for monitoring and debugging purposes.
 */
class ScheduleController extends Controller
{
    /**
     * Get scheduler status.
     *
     * Returns information about the scheduler's health including
     * the last heartbeat time and overall health status.
     *
     * GET /api/v1/scheduler/status
     */
    public function status(Request $request): JsonResponse
    {
        // Check authorization - only Superadmins can view scheduler status
        if (! $this->canViewSchedulerStatus($request)) {
            return response()->json([
                'errors' => [
                    [
                        'status' => '403',
                        'title' => 'Forbidden',
                        'detail' => 'You do not have permission to view scheduler status.',
                    ],
                ],
            ], 403);
        }

        $heartbeatFile = storage_path('framework/schedule-heartbeat');
        $lastRun = file_exists($heartbeatFile)
            ? Carbon::createFromTimestamp(filemtime($heartbeatFile))
            : null;

        $healthy = $lastRun !== null && $lastRun->diffInMinutes(now()) <= 5;

        return response()->json([
            'data' => [
                'type' => 'scheduler-status',
                'id' => '1',
                'attributes' => [
                    'healthy' => $healthy,
                    'last-heartbeat' => $lastRun?->toIso8601String(),
                    'last-heartbeat-human' => $lastRun?->diffForHumans(),
                    'minutes-since-heartbeat' => $lastRun?->diffInMinutes(now()),
                ],
            ],
        ]);
    }

    /**
     * Check if the user can view scheduler status.
     */
    protected function canViewSchedulerStatus(Request $request): bool
    {
        $user = $request->user();

        if ($user === null) {
            return false;
        }

        // Check if user has Superadmin role
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('Superadmin');
        }

        return false;
    }
}
