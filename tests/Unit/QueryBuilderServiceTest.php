<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\QueryBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(QueryBuilderService::class);
});

test('buildQuery returns Eloquent Builder instance', function () {
    $request = Request::create('/api/v1/organization', 'GET');

    $query = $this->service->buildQuery(Organization::class, $request);

    expect($query)->toBeInstanceOf(\Illuminate\Database\Eloquent\Builder::class);
});

test('buildFilters creates partial filter for string fields', function () {
    $structure = [
        'fields' => [
            [
                'name' => 'name',
                'type' => 'string',
                'filterable' => true,
            ],
        ],
        'filters' => [],
    ];

    $method = new ReflectionMethod(QueryBuilderService::class, 'buildFilters');
    $method->setAccessible(true);

    $filters = $method->invoke($this->service, Organization::class, $structure);

    expect($filters)->toBeArray()
        ->and($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(AllowedFilter::class);
});

test('buildFilters creates exact filter for UUID fields', function () {
    $structure = [
        'fields' => [
            [
                'name' => 'id',
                'type' => 'uuid',
                'filterable' => true,
            ],
        ],
        'filters' => [],
    ];

    $method = new ReflectionMethod(QueryBuilderService::class, 'buildFilters');
    $method->setAccessible(true);

    $filters = $method->invoke($this->service, Organization::class, $structure);

    expect($filters)->toBeArray()
        ->and($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(AllowedFilter::class);
});

test('buildFilters creates exact filter for boolean fields', function () {
    $structure = [
        'fields' => [
            [
                'name' => 'is_active',
                'type' => 'boolean',
                'filterable' => true,
            ],
        ],
        'filters' => [],
    ];

    $method = new ReflectionMethod(QueryBuilderService::class, 'buildFilters');
    $method->setAccessible(true);

    $filters = $method->invoke($this->service, Organization::class, $structure);

    expect($filters)->toBeArray()
        ->and($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(AllowedFilter::class);
});

test('buildFilters creates date range filter for date fields', function () {
    $structure = [
        'fields' => [
            [
                'name' => 'created_at',
                'type' => 'datetime',
                'filterable' => true,
            ],
        ],
        'filters' => [],
    ];

    $method = new ReflectionMethod(QueryBuilderService::class, 'buildFilters');
    $method->setAccessible(true);

    $filters = $method->invoke($this->service, Organization::class, $structure);

    expect($filters)->toBeArray()
        ->and($filters)->toHaveCount(1)
        ->and($filters[0])->toBeInstanceOf(AllowedFilter::class);
});

test('buildFilters skips non-filterable fields', function () {
    $structure = [
        'fields' => [
            [
                'name' => 'name',
                'type' => 'string',
                'filterable' => false,
            ],
            [
                'name' => 'slug',
                'type' => 'string',
                'filterable' => true,
            ],
        ],
        'filters' => [],
    ];

    $method = new ReflectionMethod(QueryBuilderService::class, 'buildFilters');
    $method->setAccessible(true);

    $filters = $method->invoke($this->service, Organization::class, $structure);

    expect($filters)->toHaveCount(1);
});

test('buildSorts creates allowed sorts from sortable fields', function () {
    $structure = [
        'fields' => [
            [
                'name' => 'name',
                'sortable' => true,
            ],
            [
                'name' => 'created_at',
                'sortable' => true,
            ],
            [
                'name' => 'slug',
                'sortable' => false,
            ],
        ],
        'sorts' => [],
    ];

    $method = new ReflectionMethod(QueryBuilderService::class, 'buildSorts');
    $method->setAccessible(true);

    $sorts = $method->invoke($this->service, $structure);

    expect($sorts)->toBeArray()
        ->and($sorts)->toHaveCount(2)
        ->and($sorts[0])->toBeInstanceOf(AllowedSort::class)
        ->and($sorts[1])->toBeInstanceOf(AllowedSort::class);
});

test('applySearch adds ILIKE search to query', function () {
    Organization::factory()->create(['name' => 'Acme Corporation']);
    Organization::factory()->create(['name' => 'Test Company']);

    $request = Request::create('/api/v1/organization?search=Acme', 'GET');

    $query = $this->service->buildQuery(Organization::class, $request);
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Acme Corporation');
});

test('applySearch is case insensitive', function () {
    Organization::factory()->create(['name' => 'Acme Corporation']);

    $request = Request::create('/api/v1/organization?search=acme', 'GET');

    $query = $this->service->buildQuery(Organization::class, $request);
    $results = $query->get();

    expect($results)->toHaveCount(1);
});

test('applySearch searches across multiple fields', function () {
    Organization::factory()->create(['name' => 'Acme Corp', 'vat_number' => 'BE0123']);
    Organization::factory()->create(['name' => 'Test Corp', 'vat_number' => 'BE0456']);

    // Search by name
    $request = Request::create('/api/v1/organization?search=Acme', 'GET');
    $query = $this->service->buildQuery(Organization::class, $request);
    expect($query->get())->toHaveCount(1);

    // Search by VAT number
    $request = Request::create('/api/v1/organization?search=BE0456', 'GET');
    $query = $this->service->buildQuery(Organization::class, $request);
    expect($query->get())->toHaveCount(1);
});

test('buildQuery applies filters from request', function () {
    Organization::factory()->create(['is_active' => true]);
    Organization::factory()->create(['is_active' => false]);

    $request = Request::create('/api/v1/organization?filter[is_active]=true', 'GET');

    $query = $this->service->buildQuery(Organization::class, $request);
    $results = $query->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->is_active)->toBeTrue();
});

test('buildQuery applies sorts from request', function () {
    Organization::factory()->create(['name' => 'Zebra']);
    Organization::factory()->create(['name' => 'Alpha']);

    $request = Request::create('/api/v1/organization?sort=name', 'GET');

    $query = $this->service->buildQuery(Organization::class, $request);
    $results = $query->get();

    expect($results->first()->name)->toBe('Alpha')
        ->and($results->last()->name)->toBe('Zebra');
});

test('buildQuery handles empty search parameter', function () {
    Organization::factory()->count(3)->create();

    $request = Request::create('/api/v1/organization?search=', 'GET');

    $query = $this->service->buildQuery(Organization::class, $request);
    $results = $query->get();

    expect($results)->toHaveCount(3);
});

test('buildQuery combines filters, sorts, and search', function () {
    Organization::factory()->create(['name' => 'Active Zebra', 'is_active' => true]);
    Organization::factory()->create(['name' => 'Active Alpha', 'is_active' => true]);
    Organization::factory()->create(['name' => 'Inactive Beta', 'is_active' => false]);

    $request = Request::create(
        '/api/v1/organization?filter[is_active]=true&sort=name&search=Active',
        'GET'
    );

    $query = $this->service->buildQuery(Organization::class, $request);
    $results = $query->get();

    expect($results)->toHaveCount(2)
        ->and($results->first()->name)->toBe('Active Alpha')
        ->and($results->last()->name)->toBe('Active Zebra');
});
