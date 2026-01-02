<?php

declare(strict_types=1);

use Blafast\Foundation\Services\PaginationService;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test users for pagination
    for ($i = 1; $i <= 50; $i++) {
        User::factory()->create([
            'name' => "User {$i}",
            'email' => "user{$i}@example.com",
        ]);
    }
});

test('pagination service paginates with default page size', function () {
    $service = app(PaginationService::class);
    $request = Request::create('/api/v1/users', 'GET');
    $query = User::query()->orderBy('id');

    $paginator = $service->paginate($query, $request);

    expect($paginator->perPage())->toBe(25)
        ->and($paginator->items())->toHaveCount(25)
        ->and($paginator->hasMorePages())->toBeTrue();
});

test('pagination service respects custom per_page parameter', function () {
    $service = app(PaginationService::class);
    $request = Request::create('/api/v1/users?page[per_page]=10', 'GET');
    $query = User::query()->orderBy('id');

    $paginator = $service->paginate($query, $request);

    expect($paginator->perPage())->toBe(10)
        ->and($paginator->items())->toHaveCount(10);
});

test('pagination service caps at maximum page size', function () {
    $service = app(PaginationService::class);
    $request = Request::create('/api/v1/users?page[per_page]=500', 'GET');
    $query = User::query()->orderBy('id');

    $paginator = $service->paginate($query, $request);

    expect($paginator->perPage())->toBe(100); // Capped at max
});

test('pagination service respects model custom max size', function () {
    $service = app(PaginationService::class);
    $request = Request::create('/api/v1/users?page[per_page]=75', 'GET');
    $query = User::query()->orderBy('id');

    $apiStructure = [
        'pagination' => [
            'max_size' => 50,
        ],
    ];

    $paginator = $service->paginate($query, $request, $apiStructure);

    expect($paginator->perPage())->toBe(50); // Capped at model max
});

test('pagination service formats response correctly', function () {
    $service = app(PaginationService::class);
    $request = Request::create('/api/v1/users?page[per_page]=5', 'GET');
    $query = User::query()->orderBy('id');

    $paginator = $service->paginate($query, $request);
    $response = $service->formatResponse($paginator, fn ($user) => [
        'id' => $user->id,
        'name' => $user->name,
    ]);

    expect($response)->toHaveKeys(['data', 'links', 'meta'])
        ->and($response['data'])->toHaveCount(5)
        ->and($response['links'])->toHaveKeys(['first', 'prev', 'next'])
        ->and($response['meta'])->toHaveKey('page')
        ->and($response['meta']['page'])->toHaveKeys(['per_page', 'has_more'])
        ->and($response['meta']['page']['per_page'])->toBe(5)
        ->and($response['meta']['page']['has_more'])->toBeTrue();
});

test('pagination links are properly formatted', function () {
    $service = app(PaginationService::class);
    $request = Request::create('/api/v1/users?page[per_page]=10', 'GET');
    $query = User::query()->orderBy('id');

    $paginator = $service->paginate($query, $request);
    $response = $service->formatResponse($paginator, fn ($user) => [
        'id' => $user->id,
        'name' => $user->name,
    ]);

    expect($response['links']['first'])->toBeString()
        ->and($response['links']['prev'])->toBeNull() // First page has no prev
        ->and($response['links']['next'])->toBeString();
});

test('pagination navigates correctly with cursor', function () {
    $service = app(PaginationService::class);
    $query = User::query()->orderBy('id');

    // Get first page
    $request1 = Request::create('/api/v1/users?page[per_page]=10', 'GET');
    $paginator1 = $service->paginate($query->clone(), $request1);
    $firstPageItems = collect($paginator1->items())->pluck('id')->toArray();

    // Extract cursor for next page
    $nextCursor = $paginator1->nextCursor()?->encode();

    // Get second page
    $request2 = Request::create("/api/v1/users?page[per_page]=10&page[cursor]={$nextCursor}", 'GET');
    $paginator2 = $service->paginate($query->clone(), $request2);
    $secondPageItems = collect($paginator2->items())->pluck('id')->toArray();

    // Verify no overlap between pages
    $intersection = array_intersect($firstPageItems, $secondPageItems);
    expect($intersection)->toBeEmpty();
});

test('pagination handles last page correctly', function () {
    $service = app(PaginationService::class);

    // Create only 15 users (less than default page size)
    User::query()->delete();
    for ($i = 1; $i <= 15; $i++) {
        User::factory()->create();
    }

    $request = Request::create('/api/v1/users?page[per_page]=25', 'GET');
    $query = User::query()->orderBy('id');

    $paginator = $service->paginate($query, $request);
    $response = $service->formatResponse($paginator, fn ($user) => [
        'id' => $user->id,
        'name' => $user->name,
    ]);

    expect($response['data'])->toHaveCount(15)
        ->and($response['meta']['page']['has_more'])->toBeFalse()
        ->and($response['links']['next'])->toBeNull();
});

test('pagination uses configured parameter names', function () {
    // Verify cursor_name and size_name from config are used
    $cursorName = config('blafast-fundation.api.pagination.cursor_name');
    $sizeName = config('blafast-fundation.api.pagination.size_name');

    expect($cursorName)->toBe('cursor')
        ->and($sizeName)->toBe('per_page');
});
