<?php

declare(strict_types=1);

use Blafast\Foundation\Api\ApiStructureBuilder;
use Blafast\Foundation\Models\Organization;

test('builder generates correct structure', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->label('organizations.label')
        ->string('name', 'organizations.fields.name', searchable: true)
        ->string('slug', 'organizations.fields.slug')
        ->build();

    expect($structure)->toHaveKeys(['label', 'slug', 'fields'])
        ->and($structure['label'])->toBe('organizations.label')
        ->and($structure['slug'])->toBe('organization')
        ->and($structure['fields'])->toHaveCount(2);
});

test('builder auto-generates slug from model class', function () {
    $structure = ApiStructureBuilder::make(Organization::class)->build();

    expect($structure['slug'])->toBe('organization');
});

test('builder can override slug', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->slug('custom-slug')
        ->build();

    expect($structure['slug'])->toBe('custom-slug');
});

test('builder adds uuid field correctly', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->uuid('id', 'organizations.fields.id')
        ->build();

    $idField = $structure['fields'][0];

    expect($idField['name'])->toBe('id')
        ->and($idField['type'])->toBe('uuid')
        ->and($idField['sortable'])->toBeTrue()
        ->and($idField['filterable'])->toBeTrue();
});

test('builder adds string field correctly', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->string('name', 'organizations.fields.name')
        ->build();

    $nameField = $structure['fields'][0];

    expect($nameField['name'])->toBe('name')
        ->and($nameField['type'])->toBe('string')
        ->and($nameField['searchable'])->toBeTrue()
        ->and($nameField['sortable'])->toBeTrue();
});

test('builder adds decimal field with places', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->decimal('price', 2, 'fields.price')
        ->build();

    $priceField = $structure['fields'][0];

    expect($priceField['type'])->toBe('decimal:2')
        ->and($priceField['sortable'])->toBeTrue()
        ->and($priceField['filterable'])->toBeTrue();
});

test('builder adds relation field correctly', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->relation('category', 'name', 'fields.category')
        ->build();

    $categoryField = $structure['fields'][0];

    expect($categoryField['name'])->toBe('category')
        ->and($categoryField['type'])->toBe('relation')
        ->and($categoryField['relation_name'])->toBe('category')
        ->and($categoryField['relation_field'])->toBe('name');
});

test('builder sets sortable fields', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->string('name')
        ->string('email')
        ->sortable('name', 'email', 'created_at')
        ->build();

    expect($structure['sorts'])->toHaveCount(3)
        ->and($structure['sorts'])->toContain('name', 'email', 'created_at');
});

test('builder sets filterable fields', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->string('name')
        ->filterable('name', 'status')
        ->build();

    expect($structure['filters'])->toHaveCount(2)
        ->and($structure['filters'])->toContain('name', 'status');
});

test('builder sets searchable fields', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->string('name')
        ->searchable('name', 'description')
        ->build();

    expect($structure['search']['fields'])->toHaveCount(2)
        ->and($structure['search']['fields'])->toContain('name', 'description')
        ->and($structure['search']['strategy'])->toBe('like');
});

test('builder sets full-text search', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->fullTextSearch('name', 'description')
        ->build();

    expect($structure['search']['strategy'])->toBe('full_text')
        ->and($structure['search']['fields'])->toContain('name', 'description');
});

test('builder sets allowed includes', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->includes('users', 'posts', 'comments')
        ->build();

    expect($structure['allowed_includes'])->toHaveCount(3)
        ->and($structure['allowed_includes'])->toContain('users', 'posts', 'comments');
});

test('builder sets media collections', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->mediaCollection('images', maxFiles: 10, mimes: ['image/jpeg', 'image/png'])
        ->build();

    expect($structure['media_collections'])->toHaveKey('images')
        ->and($structure['media_collections']['images']['max_files'])->toBe(10)
        ->and($structure['media_collections']['images']['accepted_mimes'])->toContain('image/jpeg', 'image/png');
});

test('builder sets pagination', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->pagination(default: 50, max: 200)
        ->build();

    expect($structure['pagination']['default_size'])->toBe(50)
        ->and($structure['pagination']['max_size'])->toBe(200);
});

test('builder supports fluent chaining', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->label('organizations.label')
        ->slug('orgs')
        ->uuid('id')
        ->string('name')
        ->decimal('balance', 2)
        ->boolean('is_active')
        ->date('founded_at')
        ->datetime('created_at')
        ->relation('owner', 'name')
        ->sortable('name', 'created_at')
        ->filterable('name', 'is_active')
        ->searchable('name')
        ->includes('owner', 'members')
        ->pagination(25, 100)
        ->build();

    expect($structure)->toHaveKeys(['label', 'slug', 'fields', 'sorts', 'filters', 'search', 'allowed_includes', 'pagination'])
        ->and($structure['fields'])->toHaveCount(7);
});

test('builder handles all field types', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->uuid('id')
        ->string('name')
        ->text('description')
        ->integer('count')
        ->float('rating')
        ->decimal('price', 2)
        ->boolean('active')
        ->date('start_date')
        ->datetime('created_at')
        ->json('metadata')
        ->enum('status', ['active', 'inactive'])
        ->relation('category', 'name')
        ->build();

    expect($structure['fields'])->toHaveCount(12);
});

test('builder removes duplicate sorts', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->sortable('name', 'email')
        ->sortable('name', 'status') // 'name' is duplicate
        ->build();

    expect($structure['sorts'])->toHaveCount(3)
        ->and($structure['sorts'])->toContain('name', 'email', 'status');
});

test('builder only includes non-empty sections', function () {
    $structure = ApiStructureBuilder::make(Organization::class)
        ->string('name')
        ->build();

    expect($structure)->toHaveKeys(['label', 'slug', 'fields'])
        ->and($structure)->not->toHaveKey('filters')
        ->and($structure)->not->toHaveKey('sorts')
        ->and($structure)->not->toHaveKey('allowed_includes')
        ->and($structure)->not->toHaveKey('media_collections')
        ->and($structure)->not->toHaveKey('pagination');
});
