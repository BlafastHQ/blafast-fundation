<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Service for transforming media items into API responses.
 *
 * Handles URL generation, conversions, and signed URLs for private storage.
 */
class FileService
{
    /**
     * Transform a Media item into JSON:API format.
     *
     * @param  bool  $detailed  Include additional metadata like custom properties and timestamps
     * @return array<string, mixed>
     */
    public function transform(Media $media, bool $detailed = false): array
    {
        $data = [
            'type' => 'file',
            'id' => $media->uuid,
            'attributes' => [
                'name' => $media->name,
                'filename' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'urls' => $this->buildUrls($media),
            ],
        ];

        if ($detailed) {
            $data['attributes']['custom_properties'] = $media->custom_properties;
            $data['attributes']['created_at'] = $media->created_at?->toIso8601String() ?? now()->toIso8601String();
            $data['attributes']['updated_at'] = $media->updated_at?->toIso8601String();
            $data['attributes']['collection_name'] = $media->collection_name;
            $data['attributes']['order_column'] = $media->order_column;
        }

        return $data;
    }

    /**
     * Build URLs for the media item including conversions.
     *
     * @return array<string, string|null>
     */
    protected function buildUrls(Media $media): array
    {
        $urls = [
            'original' => $this->getUrl($media),
        ];

        // Add conversion URLs
        $generatedConversions = $media->getGeneratedConversions();

        foreach ($generatedConversions as $conversion => $isGenerated) {
            if ($isGenerated) {
                $urls[$conversion] = $this->getUrl($media, $conversion);
            } else {
                $urls[$conversion] = null;
            }
        }

        return $urls;
    }

    /**
     * Get URL for media item, with signed URL support for private disks.
     */
    protected function getUrl(Media $media, ?string $conversion = null): string
    {
        // Check if disk is private
        $disk = $media->disk;
        /** @var array<string, mixed> $diskConfig */
        $diskConfig = config("filesystems.disks.{$disk}", []);

        if ($this->isPrivateDisk($diskConfig)) {
            // Return temporary URL valid for 60 minutes
            return $media->getTemporaryUrl(
                now()->addMinutes(60),
                $conversion ?? ''
            );
        }

        // Return public URL
        return $conversion
            ? $media->getUrl($conversion)
            : $media->getUrl();
    }

    /**
     * Determine if a disk is private.
     *
     * @param  array<string, mixed>  $config
     */
    protected function isPrivateDisk(array $config): bool
    {
        // Check visibility setting
        if (($config['visibility'] ?? 'public') === 'private') {
            return true;
        }

        // S3 disks are considered private by default for security
        if (str_starts_with($config['driver'] ?? '', 's3')) {
            return true;
        }

        return false;
    }
}
