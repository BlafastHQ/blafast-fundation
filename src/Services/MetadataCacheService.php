<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Events\MetadataCacheInvalidated;
use Blafast\Foundation\Events\MetadataCacheMiss;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;

/**
 * Service for managing metadata caching with organization scoping.
 *
 * Provides centralized cache management for:
 * - Model metadata (structure, fields, filters, etc.)
 * - Menu definitions
 * - Settings
 *
 * Features:
 * - Cache tagging for targeted invalidation
 * - Organization-scoped cache keys
 * - TTL configuration
 * - Fallback for non-tagging drivers
 * - Cache warming
 * - Monitoring support
 */
class MetadataCacheService
{
    private const DEFAULT_TTL = 600; // 10 minutes

    private const PREFIX = 'blafast:metadata:';

    public function __construct(
        private OrganizationContext $context,
    ) {}

    /**
     * Get cached value or compute and cache.
     *
     * @param  string  $key  Cache key (will be prefixed and scoped)
     * @param  array<int, string>  $tags  Cache tags for invalidation
     * @param  callable  $callback  Function to compute value on cache miss
     * @param  int|null  $ttl  Time to live in seconds (null = use default)
     */
    public function remember(
        string $key,
        array $tags,
        callable $callback,
        ?int $ttl = null
    ): mixed {
        // Skip caching if disabled in config
        if (! config('blafast-fundation.cache.enabled', true)) {
            return $callback();
        }

        $cacheKey = $this->buildKey($key);
        $cacheTags = $this->buildTags($tags);
        $ttl = $ttl ?? $this->getTtl();

        if ($this->supportsTagging()) {
            return Cache::tags($cacheTags)
                ->remember($cacheKey, $ttl, function () use ($callback, $cacheKey) {
                    $result = $callback();
                    $this->recordCacheMiss($cacheKey);

                    return $result;
                });
        }

        // Fallback for drivers without tagging (file, database)
        return Cache::remember($cacheKey, $ttl, $callback);
    }

    /**
     * Invalidate cache by tags.
     *
     * @param  array<int, string>  $tags  Tags to invalidate
     */
    public function invalidateByTags(array $tags): void
    {
        if ($this->supportsTagging()) {
            Cache::tags($this->buildTags($tags))->flush();
        } else {
            // For non-tagging drivers, increment version to invalidate
            $this->invalidateByPattern($tags);
        }

        event(new MetadataCacheInvalidated($tags));
    }

    /**
     * Invalidate all metadata for a model.
     *
     * @param  string  $modelSlug  Model slug (e.g., 'organization')
     */
    public function invalidateModel(string $modelSlug): void
    {
        $this->invalidateByTags([$modelSlug, 'model-meta']);
    }

    /**
     * Invalidate all metadata for an organization.
     *
     * @param  string  $organizationId  Organization UUID
     */
    public function invalidateOrganization(string $organizationId): void
    {
        $this->invalidateByTags(["org-{$organizationId}"]);
    }

    /**
     * Invalidate menu cache for a user.
     *
     * @param  string  $userId  User UUID
     * @param  string|null  $organizationId  Organization UUID (optional)
     */
    public function invalidateMenuForUser(string $userId, ?string $organizationId = null): void
    {
        $tags = ['menu', "user-{$userId}"];

        if ($organizationId) {
            $tags[] = "org-{$organizationId}";
        }

        $this->invalidateByTags($tags);
    }

    /**
     * Invalidate all metadata caches.
     */
    public function invalidateAll(): void
    {
        $this->invalidateByTags(['blafast-metadata']);
    }

    /**
     * Warm cache for a specific model.
     *
     * @param  class-string  $modelClass  Model class to warm
     * @param  Authenticatable|null  $user  User for permission-based warming
     */
    public function warmModel(string $modelClass, ?Authenticatable $user = null): void
    {
        /** @var ModelMetaService $metaService */
        $metaService = app(ModelMetaService::class);
        $metaService->compile($modelClass, $user);
    }

    /**
     * Warm caches for all registered models.
     */
    public function warmAllModels(): void
    {
        /** @var ModelRegistry $registry */
        $registry = app(ModelRegistry::class);

        foreach ($registry->all() as $modelClass) {
            $this->warmModel($modelClass);
        }
    }

    /**
     * Get cache statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        return [
            'driver' => config('cache.default'),
            'supports_tagging' => $this->supportsTagging(),
            'ttl' => $this->getTtl(),
            'prefix' => self::PREFIX,
        ];
    }

    /**
     * Build cache key with prefix and organization scope.
     *
     * @param  string  $key  Base cache key
     * @return string Scoped cache key
     */
    protected function buildKey(string $key): string
    {
        $orgId = $this->context->id() ?? 'global';

        return self::PREFIX."{$orgId}:{$key}";
    }

    /**
     * Build tags with organization context and base tag.
     *
     * @param  array<int, string>  $tags  User-provided tags
     * @return array<int, string> Tags with context
     */
    protected function buildTags(array $tags): array
    {
        $baseTags = ['blafast-metadata'];

        if ($this->context->hasContext()) {
            $baseTags[] = "org-{$this->context->id()}";
        }

        return array_unique(array_merge($baseTags, $tags));
    }

    /**
     * Check if the current cache driver supports tagging.
     *
     * @return bool True if tagging is supported
     */
    protected function supportsTagging(): bool
    {
        return Cache::supportsTags();
    }

    /**
     * Get TTL from configuration.
     *
     * @return int TTL in seconds
     */
    protected function getTtl(): int
    {
        return (int) config('blafast-fundation.cache.metadata_ttl', self::DEFAULT_TTL);
    }

    /**
     * Record cache miss for monitoring.
     *
     * @param  string  $key  Cache key that was missed
     */
    protected function recordCacheMiss(string $key): void
    {
        if (config('blafast-fundation.cache.monitoring_enabled', false)) {
            event(new MetadataCacheMiss($key, 'metadata'));
        }
    }

    /**
     * Invalidate by pattern for non-tagging drivers.
     *
     * For file/database cache, we store a version number
     * that gets incremented on invalidation.
     *
     * @param  array<int, string>  $tags  Tags to invalidate
     */
    protected function invalidateByPattern(array $tags): void
    {
        foreach ($tags as $tag) {
            $versionKey = self::PREFIX."version:{$tag}";
            Cache::increment($versionKey);
        }
    }
}
