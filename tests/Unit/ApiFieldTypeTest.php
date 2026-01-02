<?php

declare(strict_types=1);

use Blafast\Foundation\Enums\ApiFieldType;

test('field type is numeric', function () {
    expect(ApiFieldType::INTEGER->isNumeric())->toBeTrue()
        ->and(ApiFieldType::FLOAT->isNumeric())->toBeTrue()
        ->and(ApiFieldType::DECIMAL->isNumeric())->toBeTrue()
        ->and(ApiFieldType::STRING->isNumeric())->toBeFalse()
        ->and(ApiFieldType::UUID->isNumeric())->toBeFalse();
});

test('field type is searchable', function () {
    expect(ApiFieldType::STRING->isSearchable())->toBeTrue()
        ->and(ApiFieldType::UUID->isSearchable())->toBeTrue()
        ->and(ApiFieldType::TEXT->isSearchable())->toBeTrue()
        ->and(ApiFieldType::INTEGER->isSearchable())->toBeFalse()
        ->and(ApiFieldType::BOOLEAN->isSearchable())->toBeFalse();
});

test('field type is sortable', function () {
    expect(ApiFieldType::UUID->isSortable())->toBeTrue()
        ->and(ApiFieldType::STRING->isSortable())->toBeTrue()
        ->and(ApiFieldType::INTEGER->isSortable())->toBeTrue()
        ->and(ApiFieldType::DATE->isSortable())->toBeTrue()
        ->and(ApiFieldType::DATETIME->isSortable())->toBeTrue()
        ->and(ApiFieldType::JSON->isSortable())->toBeFalse()
        ->and(ApiFieldType::TEXT->isSortable())->toBeFalse();
});

test('field type is filterable', function () {
    expect(ApiFieldType::UUID->isFilterable())->toBeTrue()
        ->and(ApiFieldType::STRING->isFilterable())->toBeTrue()
        ->and(ApiFieldType::BOOLEAN->isFilterable())->toBeTrue()
        ->and(ApiFieldType::ENUM->isFilterable())->toBeTrue()
        ->and(ApiFieldType::JSON->isFilterable())->toBeFalse()
        ->and(ApiFieldType::TEXT->isFilterable())->toBeFalse();
});

test('field type has correct default cast', function () {
    expect(ApiFieldType::INTEGER->getDefaultCast())->toBe('integer')
        ->and(ApiFieldType::FLOAT->getDefaultCast())->toBe('float')
        ->and(ApiFieldType::DECIMAL->getDefaultCast())->toBe('decimal:2')
        ->and(ApiFieldType::BOOLEAN->getDefaultCast())->toBe('boolean')
        ->and(ApiFieldType::DATE->getDefaultCast())->toBe('date')
        ->and(ApiFieldType::DATETIME->getDefaultCast())->toBe('datetime')
        ->and(ApiFieldType::JSON->getDefaultCast())->toBe('array')
        ->and(ApiFieldType::STRING->getDefaultCast())->toBeNull();
});

test('all field types have string values', function () {
    expect(ApiFieldType::UUID->value)->toBe('uuid')
        ->and(ApiFieldType::STRING->value)->toBe('string')
        ->and(ApiFieldType::INTEGER->value)->toBe('integer')
        ->and(ApiFieldType::FLOAT->value)->toBe('float')
        ->and(ApiFieldType::DECIMAL->value)->toBe('decimal')
        ->and(ApiFieldType::BOOLEAN->value)->toBe('boolean')
        ->and(ApiFieldType::DATE->value)->toBe('date')
        ->and(ApiFieldType::DATETIME->value)->toBe('datetime')
        ->and(ApiFieldType::JSON->value)->toBe('json')
        ->and(ApiFieldType::RELATION->value)->toBe('relation')
        ->and(ApiFieldType::ENUM->value)->toBe('enum')
        ->and(ApiFieldType::TEXT->value)->toBe('text');
});
