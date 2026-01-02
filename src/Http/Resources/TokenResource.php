<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Laravel\Sanctum\PersonalAccessToken
 */
class TokenResource extends JsonResource
{
    /**
     * The plain text token (only available on creation).
     *
     * @var string|null
     */
    public ?string $plainTextToken = null;

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'auth-token',
            'id' => $this->id,
            'attributes' => [
                'name' => $this->name,
                'abilities' => $this->abilities ?? ['*'],
                'last_used_at' => $this->last_used_at?->toISOString(),
                'expires_at' => $this->expires_at?->toISOString(),
                'created_at' => $this->created_at?->toISOString(),
                'token' => $this->when($this->plainTextToken !== null, $this->plainTextToken),
            ],
            'relationships' => [
                'user' => [
                    'data' => [
                        'type' => 'user',
                        'id' => $this->tokenable_id,
                    ],
                ],
            ],
        ];
    }
}
