<?php

declare(strict_types=1);

use Blafast\Foundation\Api\ApiStructureBuilder;
use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Traits\ExposesApiStructure;
use Illuminate\Database\Eloquent\Model;

// Create a test model for testing the trait
class TestModel extends Model implements HasApiStructure
{
    use ExposesApiStructure;

    public static function apiStructure(): array
    {
        return ApiStructureBuilder::make(self::class)
            ->label('test.label')
            ->slug('test-model')
            ->uuid('id', 'test.id')
            ->string('name', 'test.name', searchable: true, sortable: true)
            ->string('email', 'test.email', searchable: true)
            ->integer('count', 'test.count', sortable: true)
            ->decimal('price', 2, 'test.price')
            ->boolean('is_active', 'test.is_active')
            ->date('started_at', 'test.started_at')
            ->relation('category', 'name', 'test.category')
            ->sortable('name', 'count', 'created_at')
            ->filterable('name', 'is_active', 'category')
            ->searchable('name', 'email')
            ->includes('category', 'tags')
            ->fullTextSearch('name', 'description')
            ->mediaCollection('images', maxFiles: 5)
            ->pagination(default: 30, max: 150)
            ->build();
    }
}

beforeEach(function () {
    // Clear cache before each test
    TestModel::clearApiStructureCache();
});

test('trait provides api structure', function () {
    $structure = TestModel::getApiStructure();

    expect($structure)->toBeArray()
        ->and($structure)->toHaveKeys(['label', 'slug', 'fields']);
});

test('trait caches api structure', function () {
    $structure1 = TestModel::getApiStructure();
    $structure2 = TestModel::getApiStructure();

    expect($structure1)->toBe($structure2); // Same instance due to caching
});

test('trait can clear cache', function () {
    TestModel::getApiStructure();
    TestModel::clearApiStructureCache();

    // After clearing, the next call should regenerate
    $structure = TestModel::getApiStructure();
    expect($structure)->toBeArray();
});

test('trait returns correct slug', function () {
    expect(TestModel::getApiSlug())->toBe('test-model');
});

test('trait returns correct label', function () {
    expect(TestModel::getApiLabel())->toBe('test.label');
});

test('trait returns all fields', function () {
    $fields = TestModel::getApiFields();

    expect($fields)->toBeArray()
        ->and($fields)->not->toBeEmpty();
});

test('trait returns specific field', function () {
    $field = TestModel::getApiField('name');

    expect($field)->toBeArray()
        ->and($field['name'])->toBe('name')
        ->and($field['label'])->toBe('test.name');
});

test('trait returns null for non-existent field', function () {
    $field = TestModel::getApiField('nonexistent');

    expect($field)->toBeNull();
});

test('trait returns sortable fields', function () {
    $sorts = TestModel::getApiSorts();

    expect($sorts)->toBeArray()
        ->and($sorts)->toContain('name', 'count', 'created_at');
});

test('trait returns filterable fields', function () {
    $filters = TestModel::getApiFilters();

    expect($filters)->toBeArray()
        ->and($filters)->toContain('name', 'is_active', 'category');
});

test('trait returns searchable fields', function () {
    $searchable = TestModel::getApiSearchableFields();

    expect($searchable)->toBeArray()
        ->and($searchable)->toContain('name', 'description');
});

test('trait returns search strategy', function () {
    expect(TestModel::getApiSearchStrategy())->toBe('full_text');
});

test('trait returns allowed includes', function () {
    $includes = TestModel::getApiIncludes();

    expect($includes)->toBeArray()
        ->and($includes)->toContain('category', 'tags');
});

test('trait returns media collections', function () {
    $collections = TestModel::getApiMediaCollections();

    expect($collections)->toBeArray()
        ->and($collections)->toHaveKey('images');
});

test('trait returns specific media collection', function () {
    $collection = TestModel::getApiMediaCollection('images');

    expect($collection)->toBeArray()
        ->and($collection['max_files'])->toBe(5);
});

test('trait returns null for non-existent media collection', function () {
    $collection = TestModel::getApiMediaCollection('nonexistent');

    expect($collection)->toBeNull();
});

test('trait returns pagination settings', function () {
    $pagination = TestModel::getApiPagination();

    expect($pagination)->toBeArray()
        ->and($pagination['default_size'])->toBe(30)
        ->and($pagination['max_size'])->toBe(150);
});

test('trait checks if field is sortable', function () {
    expect(TestModel::isApiFieldSortable('name'))->toBeTrue()
        ->and(TestModel::isApiFieldSortable('email'))->toBeFalse();
});

test('trait checks if field is filterable', function () {
    expect(TestModel::isApiFieldFilterable('name'))->toBeTrue()
        ->and(TestModel::isApiFieldFilterable('email'))->toBeFalse();
});

test('trait checks if field is searchable', function () {
    expect(TestModel::isApiFieldSearchable('name'))->toBeTrue()
        ->and(TestModel::isApiFieldSearchable('count'))->toBeFalse();
});

test('trait checks if include is allowed', function () {
    expect(TestModel::isApiIncludeAllowed('category'))->toBeTrue()
        ->and(TestModel::isApiIncludeAllowed('posts'))->toBeFalse();
});

test('trait derives sortable from fields when not explicitly set', function () {
    // Create a model without explicit sorts
    $model = new class extends Model implements HasApiStructure
    {
        use ExposesApiStructure;

        public static function apiStructure(): array
        {
            return ApiStructureBuilder::make(self::class)
                ->label('test')
                ->string('name', sortable: true)
                ->string('email', sortable: false)
                ->build();
        }
    };

    $sorts = $model::getApiSorts();

    expect($sorts)->toContain('name')
        ->and($sorts)->not->toContain('email');
});

test('trait derives filterable from fields when not explicitly set', function () {
    // Create a model without explicit filters
    $model = new class extends Model implements HasApiStructure
    {
        use ExposesApiStructure;

        public static function apiStructure(): array
        {
            return ApiStructureBuilder::make(self::class)
                ->label('test')
                ->string('name', filterable: true)
                ->string('email', filterable: false)
                ->build();
        }
    };

    $filters = $model::getApiFilters();

    expect($filters)->toContain('name')
        ->and($filters)->not->toContain('email');
});

test('trait derives searchable from fields when not explicitly set', function () {
    // Create a model without explicit search fields
    $model = new class extends Model implements HasApiStructure
    {
        use ExposesApiStructure;

        public static function apiStructure(): array
        {
            return ApiStructureBuilder::make(self::class)
                ->label('test')
                ->string('name', searchable: true)
                ->string('email', searchable: false)
                ->build();
        }
    };

    $searchable = $model::getApiSearchableFields();

    expect($searchable)->toContain('name')
        ->and($searchable)->not->toContain('email');
});

test('trait returns default pagination when not set', function () {
    // Create a model without pagination settings
    $model = new class extends Model implements HasApiStructure
    {
        use ExposesApiStructure;

        public static function apiStructure(): array
        {
            return ApiStructureBuilder::make(self::class)
                ->label('test')
                ->build();
        }
    };

    $pagination = $model::getApiPagination();

    expect($pagination['default_size'])->toBe(25)
        ->and($pagination['max_size'])->toBe(100);
});
