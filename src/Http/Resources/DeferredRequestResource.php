<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Resources;

use Blafast\Foundation\Models\DeferredApiRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON:API resource for deferred API requests.
 *
 * Formats deferred request data according to JSON:API specification.
 *
 * @property DeferredApiRequest $resource
 */
class DeferredRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $deferred = $this->resource;

        return [
            'type' => 'deferred-request',
            'id' => $deferred->id,
            'attributes' => [
                'http_method' => $deferred->http_method,
                'endpoint' => $deferred->endpoint,
                'status' => $deferred->status->value,
                'progress' => $deferred->progress,
                'progress_message' => $deferred->progress_message,
                'result' => $deferred->isCompleted() ? $deferred->result : null,
                'result_status_code' => $deferred->result_status_code,
                'error_code' => $deferred->error_code,
                'error_message' => $deferred->error_message,
                'attempts' => $deferred->attempts,
                'max_attempts' => $deferred->max_attempts,
                'priority' => $deferred->priority,
                'started_at' => $deferred->started_at?->toIso8601String(),
                'completed_at' => $deferred->completed_at?->toIso8601String(),
                'expires_at' => $deferred->expires_at->toIso8601String(),
                'created_at' => $deferred->created_at->toIso8601String(),
                'updated_at' => $deferred->updated_at->toIso8601String(),
            ],
            'relationships' => [
                'user' => [
                    'data' => [
                        'type' => 'user',
                        'id' => $deferred->user_id,
                    ],
                ],
                'organization' => [
                    'data' => [
                        'type' => 'organization',
                        'id' => $deferred->organization_id,
                    ],
                ],
            ],
            'links' => [
                'self' => route('api.v1.deferred.show', ['id' => $deferred->id]),
                'poll' => route('api.v1.deferred.show', ['id' => $deferred->id]),
            ],
        ];
    }
}
