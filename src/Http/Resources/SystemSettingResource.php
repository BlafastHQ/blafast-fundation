<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Resources;

use Blafast\Foundation\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * JSON:API resource for system settings.
 *
 * @property SystemSetting $resource
 */
class SystemSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $setting = $this->resource;

        return [
            'type' => 'system-setting',
            'id' => $setting->id,
            'attributes' => [
                'key' => $setting->key,
                'value' => $setting->getTypedValue(),
                'type' => $setting->type,
                'group' => $setting->group,
                'description' => $setting->description,
                'is_public' => $setting->is_public,
                'created_at' => $setting->created_at?->toIso8601String(),
                'updated_at' => $setting->updated_at?->toIso8601String(),
            ],
        ];
    }
}
