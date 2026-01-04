<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Controllers\Api\V1;

use Blafast\Foundation\Enums\DeferredRequestStatus;
use Blafast\Foundation\Http\Resources\DeferredRequestResource;
use Blafast\Foundation\Jobs\ProcessDeferredApiRequest;
use Blafast\Foundation\Models\DeferredApiRequest;
use Blafast\Foundation\Services\PaginationService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller for deferred API request endpoints.
 *
 * Provides endpoints to:
 * - List user's deferred requests
 * - Check status of specific request
 * - Cancel pending requests
 * - Retry failed requests
 */
class DeferredRequestController extends Controller
{
    use AuthorizesRequests;

    /**
     * Create a new controller instance.
     */
    public function __construct(
        private PaginationService $pagination,
    ) {}

    /**
     * Get paginated list of deferred requests for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DeferredApiRequest::class);

        $query = DeferredApiRequest::forUser($request->user())
            ->latest();

        // Apply status filter
        if ($statusFilter = $request->input('filter.status')) {
            $statuses = explode(',', $statusFilter);
            $query->whereIn('status', $statuses);
        }

        // Paginate results
        $paginator = $this->pagination->paginate($query, $request);

        return response()->json(
            $this->pagination->formatResponse(
                $paginator,
                fn ($request) => new DeferredRequestResource($request)
            )
        );
    }

    /**
     * Get status and result of a specific deferred request.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $deferred = DeferredApiRequest::forUser($request->user())->findOrFail($id);

        $this->authorize('view', $deferred);

        return response()->json([
            'data' => new DeferredRequestResource($deferred),
        ]);
    }

    /**
     * Cancel a pending deferred request.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $deferred = DeferredApiRequest::forUser($request->user())->findOrFail($id);

        $this->authorize('cancel', $deferred);

        if (!$deferred->isPending()) {
            return response()->json([
                'errors' => [[
                    'status' => '409',
                    'code' => 'CANNOT_CANCEL',
                    'title' => 'Cannot Cancel',
                    'detail' => 'Only pending requests can be cancelled.',
                ]],
            ], 409);
        }

        $deferred->markAsCancelled();

        return response()->json([
            'data' => new DeferredRequestResource($deferred),
        ]);
    }

    /**
     * Retry a failed deferred request.
     */
    public function retry(Request $request, string $id): JsonResponse
    {
        $deferred = DeferredApiRequest::forUser($request->user())->findOrFail($id);

        $this->authorize('retry', $deferred);

        if (!$deferred->canRetry()) {
            return response()->json([
                'errors' => [[
                    'status' => '409',
                    'code' => 'CANNOT_RETRY',
                    'title' => 'Cannot Retry',
                    'detail' => 'This request cannot be retried. It may have exceeded max attempts or is not in a failed state.',
                ]],
            ], 409);
        }

        // Reset to pending and dispatch job again
        $deferred->update(['status' => DeferredRequestStatus::Pending]);
        ProcessDeferredApiRequest::dispatch($deferred);

        return response()->json([
            'data' => new DeferredRequestResource($deferred),
        ]);
    }
}
