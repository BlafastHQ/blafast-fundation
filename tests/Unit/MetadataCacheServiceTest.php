<?php

declare(strict_types=1);

use Blafast\Foundation\Events\MetadataCacheInvalidated;
use Blafast\Foundation\Events\MetadataCacheMiss;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->context = Mockery::mock(OrganizationContext::class);
    $this->registry = Mockery::mock(ModelRegistry::class);

    $this->service = new MetadataCacheService(
        $this->context,
        $this->registry
    );

    // Enable caching and monitoring for tests
    Config::set('blafast-fundation.cache.enabled', true);
    Config::set('blafast-fundation.cache.monitoring_enabled', true);
});

afterEach(function () {
    Mockery::close();
});

test('remember caches data with tags when tagging is supported', function () {
    $this->context->shouldReceive('id')->andReturn('123');
    $this->context->shouldReceive('hasContext')->andReturn(true);

    Cache::shouldReceive('getStore->getConfig')
        ->andReturn(['driver' => 'redis']);

    Cache::shouldReceive('tags')
        ->once()
        ->with(Mockery::on(function ($tags) {
            return in_array('blafast-metadata', $tags) && in_array('org-123', $tags);
        }))
        ->andReturnSelf();

    Cache::shouldReceive('remember')
        ->once()
        ->andReturn('cached-value');

    $result = $this->service->remember('test-key', ['test-tag'], fn () => 'fresh-value');

    expect($result)->toBe('cached-value');
});

test('remember uses fallback without tagging', function () {
    $this->context->shouldReceive('id')->andReturn('123');

    Cache::shouldReceive('getStore->getConfig')
        ->andReturn(['driver' => 'file']);

    Cache::shouldReceive('remember')
        ->once()
        ->andReturn('cached-value');

    $result = $this->service->remember('test-key', ['test-tag'], fn () => 'fresh-value');

    expect($result)->toBe('cached-value');
});

test('remember fires cache miss event when value is computed', function () {
    Event::fake([MetadataCacheMiss::class]);

    $this->context->shouldReceive('id')->andReturn('123');
    $this->context->shouldReceive('hasContext')->andReturn(true);

    Cache::shouldReceive('getStore->getConfig')
        ->andReturn(['driver' => 'redis']);

    Cache::shouldReceive('tags')
        ->andReturnSelf();

    Cache::shouldReceive('remember')
        ->once()
        ->andReturnUsing(function ($key, $ttl, $callback) {
            return $callback(); // Simulate cache miss
        });

    $this->service->remember('test-key', ['test-tag'], fn () => 'fresh-value');

    Event::assertDispatched(MetadataCacheMiss::class);
});

test('invalidateByTags flushes tagged cache', function () {
    Event::fake([MetadataCacheInvalidated::class]);

    $this->context->shouldReceive('hasContext')->andReturn(true);
    $this->context->shouldReceive('id')->andReturn('123');

    Cache::shouldReceive('getStore->getConfig')
        ->andReturn(['driver' => 'redis']);

    Cache::shouldReceive('tags')
        ->once()
        ->with(Mockery::on(function ($tags) {
            return in_array('blafast-metadata', $tags)
                && in_array('org-123', $tags)
                && in_array('test-tag', $tags);
        }))
        ->andReturnSelf();

    Cache::shouldReceive('flush')
        ->once();

    $this->service->invalidateByTags(['test-tag']);

    Event::assertDispatched(MetadataCacheInvalidated::class);
});

test('invalidateModel clears cache for specific model', function () {
    $this->context->shouldReceive('hasContext')->andReturn(true);
    $this->context->shouldReceive('id')->andReturn('123');

    Cache::shouldReceive('getStore->getConfig')
        ->andReturn(['driver' => 'redis']);

    Cache::shouldReceive('tags')
        ->once()
        ->with(Mockery::on(function ($tags) {
            return in_array('organization', $tags) && in_array('model-meta', $tags);
        }))
        ->andReturnSelf();

    Cache::shouldReceive('flush')
        ->once();

    $this->service->invalidateModel('organization');
});

test('invalidateOrganization clears cache for entire organization', function () {
    $this->context->shouldReceive('hasContext')->andReturn(false);

    Cache::shouldReceive('getStore->getConfig')
        ->andReturn(['driver' => 'redis']);

    Cache::shouldReceive('tags')
        ->once()
        ->with(Mockery::on(function ($tags) {
            return in_array('org-123', $tags);
        }))
        ->andReturnSelf();

    Cache::shouldReceive('flush')
        ->once();

    $this->service->invalidateOrganization('123');
});

test('invalidateMenuForUser clears menu cache for specific user', function () {
    $this->context->shouldReceive('hasContext')->andReturn(false);

    Cache::shouldReceive('forget')
        ->once()
        ->with(Mockery::on(function ($key) {
            return str_contains($key, 'menu:user:456:org:123');
        }));

    $this->service->invalidateMenuForUser('456', '123');
});

test('invalidateAll clears all metadata cache', function () {
    $this->context->shouldReceive('hasContext')->andReturn(false);

    Cache::shouldReceive('getStore->getConfig')
        ->andReturn(['driver' => 'redis']);

    Cache::shouldReceive('tags')
        ->once()
        ->with(['blafast-metadata'])
        ->andReturnSelf();

    Cache::shouldReceive('flush')
        ->once();

    $this->service->invalidateAll();
});

test('warmModel pre-populates cache for a model', function () {
    $this->context->shouldReceive('id')->andReturn('123');
    $this->context->shouldReceive('hasContext')->andReturn(true);
    $this->registry->shouldReceive('getSlug')
        ->with(Organization::class)
        ->andReturn('organization');

    Cache::shouldReceive('getStore->getConfig')
        ->andReturn(['driver' => 'redis']);

    Cache::shouldReceive('tags')
        ->andReturnSelf();

    Cache::shouldReceive('put')
        ->once();

    $this->service->warmModel(Organization::class);
});

test('getStats returns cache statistics', function () {
    Cache::shouldReceive('getStore->getConfig')
        ->andReturn(['driver' => 'redis']);

    $stats = $this->service->getStats();

    expect($stats)->toBeArray()
        ->and($stats)->toHaveKeys(['driver', 'supports_tagging', 'ttl', 'prefix']);
});

test('buildKey includes organization context', function () {
    $this->context->shouldReceive('id')->andReturn('123');

    // Use reflection to test protected method
    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('buildKey');
    $method->setAccessible(true);

    $key = $method->invoke($this->service, 'test-key');

    expect($key)->toContain('blafast:metadata:')
        ->and($key)->toContain('123')
        ->and($key)->toContain('test-key');
});

test('buildKey uses global when no organization context', function () {
    $this->context->shouldReceive('id')->andReturn(null);

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('buildKey');
    $method->setAccessible(true);

    $key = $method->invoke($this->service, 'test-key');

    expect($key)->toContain('global');
});

test('buildTags includes organization tag when context exists', function () {
    $this->context->shouldReceive('hasContext')->andReturn(true);
    $this->context->shouldReceive('id')->andReturn('123');

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('buildTags');
    $method->setAccessible(true);

    $tags = $method->invoke($this->service, ['custom-tag']);

    expect($tags)->toContain('blafast-metadata')
        ->and($tags)->toContain('org-123')
        ->and($tags)->toContain('custom-tag');
});

test('supportsTagging returns true for redis driver', function () {
    Cache::shouldReceive('getStore->getConfig')
        ->andReturn(['driver' => 'redis']);

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('supportsTagging');
    $method->setAccessible(true);

    $result = $method->invoke($this->service);

    expect($result)->toBeTrue();
});

test('supportsTagging returns false for file driver', function () {
    Cache::shouldReceive('getStore->getConfig')
        ->andReturn(['driver' => 'file']);

    $reflection = new ReflectionClass($this->service);
    $method = $reflection->getMethod('supportsTagging');
    $method->setAccessible(true);

    $result = $method->invoke($this->service);

    expect($result)->toBeFalse();
});

test('respects cache disabled configuration', function () {
    Config::set('blafast-fundation.cache.enabled', false);

    $this->context->shouldReceive('id')->andReturn('123');

    Cache::shouldReceive('tags')->never();
    Cache::shouldReceive('remember')->never();

    $result = $this->service->remember('test-key', ['test-tag'], fn () => 'fresh-value');

    expect($result)->toBe('fresh-value');
});
