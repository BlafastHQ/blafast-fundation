<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Resources;

use Blafast\Foundation\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * JSON:API resource for activity log entries.
 *
 * Formats activity log entries according to JSON:API specification.
 *
 * @property Activity $resource
 */
class ActivityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activity = $this->resource;

        return [
            'type' => 'activity',
            'id' => $activity->id,
            'attributes' => [
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject_type' => $activity->subject_type
                    ? Str::kebab(class_basename($activity->subject_type))
                    : null,
                'subject_id' => $activity->subject_id,
                'causer_type' => $activity->causer_type
                    ? Str::kebab(class_basename($activity->causer_type))
                    : null,
                'causer_id' => $activity->causer_id,
                'properties' => $activity->properties?->toArray(),
                'created_at' => $activity->created_at?->toIso8601String(),
            ],
            'relationships' => [
                'causer' => $this->when(
                    $activity->relationLoaded('causer') && $activity->causer,
                    fn () => [
                        'data' => [
                            'type' => 'user',
                            'id' => $activity->causer->id,
                        ],
                    ]
                ),
                'subject' => $this->when(
                    $activity->relationLoaded('subject') && $activity->subject,
                    fn () => [
                        'data' => [
                            'type' => $activity->subject_type
                                ? Str::kebab(class_basename($activity->subject_type))
                                : 'unknown',
                            'id' => $activity->subject->id ?? $activity->subject_id,
                        ],
                    ]
                ),
            ],
        ];
    }
}
