<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 * Custom Media model with UUID primary key and organization support.
 *
 * Extends Spatie's base Media model to add:
 * - UUID primary keys instead of auto-increment
 * - Automatic organization context association
 * - Organization relationship
 *
 * @property string $uuid
 * @property string|null $organization_id
 * @property-read Organization|null $organization
 */
class Media extends BaseMedia
{
    use HasUuids;

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'manipulations' => 'array',
        'custom_properties' => 'array',
        'generated_conversions' => 'array',
        'responsive_images' => 'array',
    ];

    /**
     * Get the organization that owns the media.
     *
     * @return BelongsTo<Organization, Media>
     */
    public function organization(): BelongsTo
    {
        /** @phpstan-ignore return.type */
        return $this->belongsTo(Organization::class);
    }

    /**
     * Boot the model.
     *
     * Automatically associates media with current organization context.
     */
    protected static function booted(): void
    {
        static::creating(function (Media $media) {
            $context = app(OrganizationContext::class);

            if ($context->hasContext()) {
                $media->organization_id = $context->id();
            }
        });
    }
}
