<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Controllers\Api\V1;

use Blafast\Foundation\Http\Resources\ActivityResource;
use Blafast\Foundation\Models\Activity;
use Blafast\Foundation\Services\PaginationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for activity log API endpoints.
 *
 * Provides access to the audit trail with filtering and pagination.
 * Only accessible to users with appropriate permissions (Admin, Superadmin).
 */
class ActivityLogController extends Controller
{
    use AuthorizesRequests;
    /**
     * Create a new controller instance.
     */
    public function __construct(
        private PaginationService $pagination,
    ) {}

    /**
     * Get paginated list of activities with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Activity::class);

        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->latest();

        // Apply filters
        if ($subjectType = $request->input('filter.subject_type')) {
            $query->where('subject_type', $subjectType);
        }

        if ($subjectId = $request->input('filter.subject_id')) {
            $query->where('subject_id', $subjectId);
        }

        if ($causerId = $request->input('filter.causer_id')) {
            $query->where('causer_id', $causerId);
        }

        if ($event = $request->input('filter.event')) {
            $query->where('event', $event);
        }

        if ($logName = $request->input('filter.log_name')) {
            $query->where('log_name', $logName);
        }

        if ($from = $request->input('filter.from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->input('filter.to')) {
            $query->where('created_at', '<=', $to);
        }

        // Paginate results
        $paginator = $this->pagination->paginate($query, $request);

        return response()->json(
            $this->pagination->formatResponse(
                $paginator,
                fn ($activity) => new ActivityResource($activity)
            )
        );
    }

    /**
     * Get a single activity by ID.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $activity = Activity::with(['causer', 'subject'])->findOrFail($id);

        $this->authorize('view', $activity);

        return response()->json([
            'data' => new ActivityResource($activity),
        ]);
    }
}
