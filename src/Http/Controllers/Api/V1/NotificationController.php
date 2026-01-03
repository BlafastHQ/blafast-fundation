<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Controllers\Api\V1;

use Blafast\Foundation\Http\Resources\NotificationResource;
use Blafast\Foundation\Services\PaginationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for notification API endpoints.
 *
 * Provides access to user notifications with filtering and pagination.
 * Allows users to manage their notifications (mark as read, etc.).
 */
class NotificationController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private PaginationService $pagination,
    ) {}

    /**
     * Get paginated list of notifications for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'unread_count' => 0,
                ],
            ]);
        }

        // @phpstan-ignore-next-line User has Notifiable trait
        $query = $user->notifications()
            ->latest();

        // Apply filters
        if ($request->has('filter.unread')) {
            if ($request->boolean('filter.unread')) {
                $query->whereNull('read_at');
            } else {
                $query->whereNotNull('read_at');
            }
        }

        if ($type = $request->input('filter.type')) {
            $query->where('type', $type);
        }

        // Paginate results
        $paginator = $this->pagination->paginate($query, $request);

        return response()->json(
            array_merge(
                $this->pagination->formatResponse(
                    $paginator,
                    fn ($notification) => new NotificationResource($notification)
                ),
                [
                    'meta' => array_merge(
                        $this->pagination->formatResponse($paginator, fn ($n) => $n)['meta'] ?? [],
                        [
                            // @phpstan-ignore-next-line User has Notifiable trait
                            'unread_count' => $user->unreadNotifications()->count(),
                        ]
                    ),
                ]
            )
        );
    }

    /**
     * Get a single notification by ID.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }

        // @phpstan-ignore-next-line User has Notifiable trait
        $notification = $user->notifications()->findOrFail($id);

        return response()->json([
            'data' => new NotificationResource($notification),
        ]);
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }

        // @phpstan-ignore-next-line User has Notifiable trait
        $notification = $user->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'data' => new NotificationResource($notification->fresh()),
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => 'Unauthenticated',
            ], 401);
        }

        // @phpstan-ignore-next-line User has Notifiable trait
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read',
            'meta' => [
                'unread_count' => 0,
            ],
        ]);
    }

    /**
     * Get count of unread notifications.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'meta' => [
                    'unread_count' => 0,
                ],
            ]);
        }

        return response()->json([
            'meta' => [
                // @phpstan-ignore-next-line User has Notifiable trait
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }
}
