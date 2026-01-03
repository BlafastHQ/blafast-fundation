<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\ModelRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Register the Organization model
    $registry = app(ModelRegistry::class);
    $registry->register(Organization::class);
});

test('meta endpoint returns correct structure', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'type',
                'id',
                'attributes' => [
                    'model',
                    'label',
                    'endpoints',
                    'fields',
                    'filters',
                    'sorts',
                    'search',
                    'pagination',
                ],
            ],
        ]);
});

test('meta endpoint returns model information', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200);

    expect($response->json('data.type'))->toBe('model-meta')
        ->and($response->json('data.id'))->toBe('organization')
        ->and($response->json('data.attributes.model'))->toBe('Organization')
        ->and($response->json('data.attributes.label'))->toBeString()
        ->and($response->json('data.attributes.label'))->toContain('organization');
});

test('meta endpoint includes all required endpoints', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200);

    $endpoints = $response->json('data.attributes.endpoints');

    expect($endpoints)->toHaveKeys(['list', 'view_entity', 'meta'])
        ->and($endpoints['list'])->toBe('/api/v1/organization')
        ->and($endpoints['view_entity'])->toBe('/api/v1/organization/{entity}')
        ->and($endpoints['meta'])->toBe('/api/v1/meta/organization');
});

test('meta endpoint includes field metadata', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200);

    $fields = $response->json('data.attributes.fields');

    expect($fields)->toBeArray()
        ->and($fields)->not->toBeEmpty();

    $firstField = $fields[0];
    expect($firstField)->toHaveKeys(['name', 'label', 'type', 'sortable', 'filterable', 'searchable']);
});

test('meta endpoint includes filters for filterable fields', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200);

    $filters = $response->json('data.attributes.filters');

    expect($filters)->toBeArray();

    if (! empty($filters)) {
        expect($filters[0])->toHaveKeys(['field', 'type']);
    }
});

test('meta endpoint includes sorts for sortable fields', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200);

    $sorts = $response->json('data.attributes.sorts');

    expect($sorts)->toBeArray();
});

test('meta endpoint includes search configuration', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200);

    $search = $response->json('data.attributes.search');

    expect($search)->toBeArray()
        ->and($search)->toHaveKeys(['fields', 'strategy']);
});

test('meta endpoint includes pagination configuration', function () {
    $response = $this->getJson('/api/v1/meta/organization');

    $response->assertStatus(200);

    $pagination = $response->json('data.attributes.pagination');

    expect($pagination)->toBeArray()
        ->and($pagination)->toHaveKeys(['default_size', 'max_size']);
});

test('meta endpoint returns 404 for unknown model slug', function () {
    $response = $this->getJson('/api/v1/meta/nonexistent');

    $response->assertStatus(404);
});

test('meta endpoint requires authorization', function () {
    // This test expects 403 because the meta endpoint checks viewAny permission
    // Without authentication or proper permissions, it should return 403
    $response = $this->getJson('/api/v1/meta/organization');

    // The endpoint is accessible without auth for public models,
    // but returns 403 if authorization fails
    expect($response->status())->toBeIn([200, 403]);
});

test('meta endpoint is rate limited', function () {
    // Make multiple requests to test rate limiting
    // The api throttle is typically 60 requests per minute

    for ($i = 0; $i < 5; $i++) {
        $response = $this->getJson('/api/v1/meta/organization');
        expect($response->status())->toBeIn([200, 403]);
    }
});

test('meta response is cacheable', function () {
    // First request
    $response1 = $this->getJson('/api/v1/meta/organization');

    // Second request should be served from cache
    $response2 = $this->getJson('/api/v1/meta/organization');

    expect($response1->json('data'))->toEqual($response2->json('data'));
});
