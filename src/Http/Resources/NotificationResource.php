<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Resources;

use Blafast\Foundation\Models\DatabaseNotification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * JSON:API resource for notifications.
 *
 * Formats notification entries according to JSON:API specification.
 *
 * @property DatabaseNotification $resource
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $notification = $this->resource;

        return [
            'type' => 'notification',
            'id' => $notification->id,
            'attributes' => [
                'type' => $notification->type
                    ? Str::kebab(class_basename($notification->type))
                    : null,
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ],
            'relationships' => [
                'notifiable' => [
                    'data' => [
                        'type' => $notification->notifiable_type
                            ? Str::kebab(class_basename($notification->notifiable_type))
                            : 'unknown',
                        'id' => $notification->notifiable_id,
                    ],
                ],
            ],
        ];
    }
}
