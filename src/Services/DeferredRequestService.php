<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Enums\DeferredRequestStatus;
use Blafast\Foundation\Http\Middleware\ResolveOrganizationContext;
use Blafast\Foundation\Jobs\ProcessDeferredApiRequest;
use Blafast\Foundation\Models\DeferredApiRequest;
use Blafast\Foundation\Models\DeferredEndpointConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stores a request for deferred (background) execution and shapes the 202
 * contract. Shared by DeferredRequestMiddleware (opt-in per endpoint) and
 * ModelMethodController (task 27 / M7: `->queued()` RPC methods route through
 * the deferred infrastructure instead of returning a fabricated
 * `executed_at = now()` with no job id and no retrievable result).
 */
class DeferredRequestService
{
    public function __construct(
        private OrganizationContext $orgContext
    ) {}

    /**
     * Whether the current request can be stored for deferred execution.
     *
     * False during a deferred replay (the server-side loop guard), without an
     * authenticated user, in global context (organization_id is NOT NULL — the
     * M11 degradation), when the subsystem is disabled, or when the request
     * carries file uploads (M10: the encrypted:json cast cannot round-trip
     * them and the temp files are gone before the job runs).
     */
    public function canDefer(Request $request): bool
    {
        return config('blafast-fundation.deferred.enabled', true)
            && $request->attributes->get('blafast.deferred_execution') !== true
            && $request->user() !== null
            && $this->orgContext->hasContext()
            && $request->allFiles() === [];
    }

    /**
     * Store the request and dispatch its background execution.
     */
    public function defer(Request $request, ?DeferredEndpointConfig $config = null): DeferredApiRequest
    {
        $deferred = DeferredApiRequest::create([
            'organization_id' => $this->orgContext->id(),
            'user_id' => $request->user()->id,
            'http_method' => $request->method(),
            'endpoint' => $request->path(),
            'payload' => $request->isMethod('GET') ? null : $request->all(),
            'query_params' => $request->query(),
            'headers' => $this->filterHeaders($request->headers->all()),
            'status' => DeferredRequestStatus::Pending,
            'priority' => $config->priority ?? config('blafast-fundation.deferred.priority', 'default'),
            'max_attempts' => 3,
            'expires_at' => now()->addSeconds($config->result_ttl ?? (int) config('blafast-fundation.deferred.result_ttl', 3600)),
        ]);

        ProcessDeferredApiRequest::dispatch($deferred);

        return $deferred;
    }

    /**
     * The 202 Accepted response: a trackable id plus the poll link where the
     * stored result becomes retrievable.
     */
    public function respond(DeferredApiRequest $deferred): JsonResponse
    {
        return response()->json([
            'data' => [
                'type' => 'deferred-request',
                'id' => $deferred->id,
                'attributes' => [
                    'status' => $deferred->status->value,
                    'endpoint' => $deferred->endpoint,
                    'http_method' => $deferred->http_method,
                    'created_at' => $deferred->created_at->toIso8601String(),
                    'expires_at' => $deferred->expires_at->toIso8601String(),
                ],
                'links' => [
                    'self' => route('api.v1.deferred.show', ['id' => $deferred->id]),
                    'poll' => route('api.v1.deferred.show', ['id' => $deferred->id]),
                ],
            ],
        ], 202);
    }

    /**
     * Filter headers to only include safe ones.
     *
     * @param  array<string, array<int, string|null>>  $headers
     * @return array<string, array<int, string|null>>
     */
    protected function filterHeaders(array $headers): array
    {
        $allowed = [
            'accept',
            'content-type',
            'accept-language',
            // The organization header name is configurable (task 23) — filter
            // by the CONFIGURED name so the replay keeps its context header.
            strtolower(ResolveOrganizationContext::headerName()),
        ];

        return array_intersect_key(
            $headers,
            array_flip(array_map('strtolower', $allowed))
        );
    }
}
