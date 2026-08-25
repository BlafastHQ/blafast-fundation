<?php

declare(strict_types=1);

use Blafast\Foundation\Dto\ModelMeta;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\ExecPermissionChecker;
use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\ModelMetaService;
use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->context = Mockery::mock(OrganizationContext::class);
    $this->context->shouldReceive('id')->andReturn(null);

    $this->permissionChecker = Mockery::mock(ExecPermissionChecker::class);
    $this->permissionChecker->shouldReceive('getExecutableMethodsForModel')->andReturn([]);

    // Task 11: compile() routes through the single guarded MetadataCacheService
    // layer now; a pass-through mock keeps these unit tests cache-free.
    $this->metaCache = Mockery::mock(MetadataCacheService::class);
    $this->metaCache->shouldReceive('remember')->andReturnUsing(fn ($key, $tags, $cb) => $cb());
    $this->metaCache->shouldReceive('invalidateModel');

    $this->service = new ModelMetaService($this->context, $this->permissionChecker, $this->metaCache);
});

afterEach(function () {
    Mockery::close();
});

test('compile returns ModelMeta instance', function () {
    $meta = $this->service->compile(Organization::class);

    expect($meta)->toBeInstanceOf(ModelMeta::class)
        ->and($meta->model)->toBe('Organization')
        ->and($meta->slug)->toBe('organization')
        ->and($meta->label)->toBeString()
        ->and($meta->label)->toContain('organization');
});

test('compile includes endpoints', function () {
    $meta = $this->service->compile(Organization::class);

    expect($meta->endpoints)->toBeArray()
        ->and($meta->endpoints)->toHaveKeys(['list', 'view_entity', 'meta'])
        ->and($meta->endpoints['list'])->toBe('/api/v1/organization')
        ->and($meta->endpoints['view_entity'])->toBe('/api/v1/organization/{entity}')
        ->and($meta->endpoints['meta'])->toBe('/api/v1/meta/organization');
});

test('compile includes fields with metadata', function () {
    $meta = $this->service->compile(Organization::class);

    expect($meta->fields)->toBeArray()
        ->and($meta->fields)->not->toBeEmpty();

    $firstField = $meta->fields[0];
    expect($firstField)->toHaveKeys(['name', 'label', 'type', 'sortable', 'filterable', 'searchable']);
});

test('compile includes search configuration', function () {
    $meta = $this->service->compile(Organization::class);

    expect($meta->search)->toBeArray()
        ->and($meta->search)->toHaveKeys(['fields', 'strategy']);
});

test('compile includes pagination configuration', function () {
    $meta = $this->service->compile(Organization::class);

    expect($meta->pagination)->toBeArray()
        ->and($meta->pagination)->toHaveKeys(['default_size', 'max_size']);
});

test('compile caches metadata', function () {
    // Task 11 (C3): compile() delegates to the single guarded cache layer.
    $calls = 0;
    $metaCache = Mockery::mock(MetadataCacheService::class);
    $metaCache->shouldReceive('remember')->andReturnUsing(function ($key, $tags, $cb) use (&$calls) {
        $calls++;

        return $cb();
    });

    $service = new ModelMetaService($this->context, $this->permissionChecker, $metaCache);
    $service->compile(Organization::class);

    expect($calls)->toBe(1);
});

test('invalidate clears cache for model', function () {
    $this->metaCache->shouldReceive('invalidateByTags')
        ->once()
        ->with(Mockery::on(fn ($tags) => in_array('organization', $tags, true)));

    $this->service->invalidate(Organization::class);
});

test('compile uses organization context in cache key', function () {
    $contextWithOrg = Mockery::mock(OrganizationContext::class);
    $contextWithOrg->shouldReceive('id')->andReturn('123');

    $permissionChecker = Mockery::mock(ExecPermissionChecker::class);
    $permissionChecker->shouldReceive('getExecutableMethodsForModel')->andReturn([]);

    $metaCache = Mockery::mock(MetadataCacheService::class);
    $metaCache->shouldReceive('remember')
        ->once()
        ->with(
            Mockery::on(fn ($key) => str_contains($key, '123')),
            Mockery::on(fn ($tags) => in_array('org-123', $tags, true)),
            Mockery::any()
        )
        ->andReturn(new ModelMeta(
            model: 'Organization',
            label: 'organizations',
            slug: 'organization',
        ));

    $service = new ModelMetaService($contextWithOrg, $permissionChecker, $metaCache);

    $service->compile(Organization::class);
});
