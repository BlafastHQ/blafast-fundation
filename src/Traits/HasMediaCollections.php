<?php

declare(strict_types=1);

namespace Blafast\Foundation\Traits;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Trait for models that support media collections.
 *
 * Automatically registers media collections and conversions based on
 * the model's apiStructure() configuration.
 *
 * Usage:
 * ```php
 * class Product extends Model implements HasMedia, HasApiStructure
 * {
 *     use HasMediaCollections;
 *
 *     public static function apiStructure(): array
 *     {
 *         return [
 *             'media_collections' => [
 *                 'images' => [
 *                     'max_files' => 20,
 *                     'accepted_mimes' => ['image/jpeg', 'image/png'],
 *                     'conversions' => ['thumb', 'preview'],
 *                     'responsive' => true,
 *                 ],
 *             ],
 *         ];
 *     }
 * }
 * ```
 */
trait HasMediaCollections
{
    use InteractsWithMedia;

    /**
     * Register media collections based on apiStructure().
     */
    public function registerMediaCollections(): void
    {
        $structure = static::getApiStructure();
        $collections = $structure['media_collections'] ?? [];

        foreach ($collections as $name => $config) {
            $collection = $this->addMediaCollection($name);

            // Limit number of files in collection
            if (isset($config['max_files'])) {
                $collection->onlyKeepLatest($config['max_files']);
            }

            // Accept only specific MIME types
            if (isset($config['accepted_mimes'])) {
                $collection->acceptsMimeTypes($config['accepted_mimes']);
            }

            // Single file collection
            if (isset($config['single']) && $config['single']) {
                $collection->singleFile();
            }

            // Use specific disk
            if (isset($config['disk'])) {
                $collection->useDisk($config['disk']);
            }
        }
    }

    /**
     * Register media conversions based on apiStructure().
     *
     * Task 25: the parameter types spatie's Media (what the HasMedia interface
     * declares) — the old narrowing to the package Media model was a fatal
     * signature incompatibility for every adopting model.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $structure = static::getApiStructure();
        $collections = $structure['media_collections'] ?? [];

        foreach ($collections as $name => $config) {
            $conversions = $config['conversions'] ?? [];

            foreach ($conversions as $conversion) {
                $this->registerConversion($conversion, $name, $config);
            }
        }
    }

    /**
     * Register a single conversion.
     *
     * @param  string  $conversion  Conversion name (e.g., 'thumb', 'preview')
     * @param  string  $collection  Collection name
     * @param  array<string, mixed>  $config  Collection configuration
     */
    protected function registerConversion(
        string $conversion,
        string $collection,
        array $config
    ): void {
        $conversionConfig = $this->getConversionConfig($conversion);

        $mediaConversion = $this->addMediaConversion($conversion)
            ->performOnCollections($collection);

        // Apply width if configured
        if (isset($conversionConfig['width'])) {
            $mediaConversion->width($conversionConfig['width']);
        }

        // Apply height if configured
        if (isset($conversionConfig['height'])) {
            $mediaConversion->height($conversionConfig['height']);
        }

        // Apply quality if configured
        if (isset($conversionConfig['quality'])) {
            $mediaConversion->quality($conversionConfig['quality']);
        }

        // Apply format if configured
        if (isset($conversionConfig['format'])) {
            $mediaConversion->format($conversionConfig['format']);
        }

        // Enable responsive images if requested
        if ($config['responsive'] ?? false) {
            $mediaConversion->withResponsiveImages();
        }

        // Task 25 (L2): honour the configured queue behaviour. The old code
        // called nonQueued() unconditionally — under a "Queue conversions by
        // default" comment — overriding both media-library's
        // queue_conversions_by_default and this package's own knob, so every
        // conversion (responsive variants included) generated inline in the
        // upload request.
        if (config('blafast-fundation.media.queue_conversions', true)) {
            $mediaConversion->queued();
        } else {
            $mediaConversion->nonQueued();
        }
    }

    /**
     * Get conversion configuration from package config.
     *
     * @param  string  $name  Conversion name
     * @return array<string, mixed>
     */
    protected function getConversionConfig(string $name): array
    {
        $conversions = config('blafast-fundation.media.conversions', []);

        return $conversions[$name] ?? [];
    }
}
