<?php

declare(strict_types=1);

namespace Blafast\Foundation\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Generates media file paths organized by organization.
 *
 * Creates a directory structure like:
 * - {organization_id}/{media_uuid}/filename.ext
 * - {organization_id}/{media_uuid}/conversions/thumb.jpg
 * - {organization_id}/{media_uuid}/responsive/filename___thumb_50.jpg
 *
 * Files without an organization are stored in a "global" directory.
 */
class OrganizationPathGenerator implements PathGenerator
{
    /**
     * Get the path for storing the original file.
     */
    public function getPath(Media $media): string
    {
        $orgId = $media->organization_id ?? 'global';

        return "{$orgId}/{$media->uuid}/";
    }

    /**
     * Get the path for storing image conversions.
     */
    public function getPathForConversions(Media $media): string
    {
        return $this->getPath($media).'conversions/';
    }

    /**
     * Get the path for storing responsive images.
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getPath($media).'responsive/';
    }
}
