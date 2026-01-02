<?php

declare(strict_types=1);

use Blafast\Foundation\Dto\ApiField;
use Blafast\Foundation\Enums\ApiFieldType;

test('api field converts to array', function () {
    $field = new ApiField(
        name: 'name',
        label: 'fields.name',
        type: ApiFieldType::STRING,
        sortable: true,
        filterable: true,
        searchable: true
    );

    $array = $field->toArray();

    expect($array)->toHaveKeys(['name', 'label', 'type', 'sortable', 'filterable', 'searchable'])
        ->and($array['name'])->toBe('name')
        ->and($array['label'])->toBe('fields.name')
        ->and($array['type'])->toBe('string')
        ->and($array['sortable'])->toBeTrue()
        ->and($array['filterable'])->toBeTrue()
        ->and($array['searchable'])->toBeTrue();
});

test('decimal field includes decimal places in type', function () {
    $field = new ApiField(
        name: 'price',
        label: 'fields.price',
        type: ApiFieldType::DECIMAL,
        decimalPlaces: 2
    );

    $array = $field->toArray();

    expect($array['type'])->toBe('decimal:2');
});

test('relation field includes relation details', function () {
    $field = new ApiField(
        name: 'category',
        label: 'fields.category',
        type: ApiFieldType::RELATION,
        relationName: 'category',
        relationField: 'name'
    );

    $array = $field->toArray();

    expect($array)->toHaveKeys(['relation_name', 'relation_field'])
        ->and($array['relation_name'])->toBe('category')
        ->and($array['relation_field'])->toBe('name');
});

test('enum field includes enum values', function () {
    $field = new ApiField(
        name: 'status',
        label: 'fields.status',
        type: ApiFieldType::ENUM,
        enumValues: ['active', 'inactive', 'pending']
    );

    $array = $field->toArray();

    expect($array)->toHaveKey('enum_values')
        ->and($array['enum_values'])->toBe(['active', 'inactive', 'pending']);
});

test('api field can be created from array', function () {
    $data = [
        'name' => 'email',
        'label' => 'fields.email',
        'type' => 'string',
        'sortable' => true,
        'filterable' => true,
        'searchable' => true,
    ];

    $field = ApiField::fromArray($data);

    expect($field->name)->toBe('email')
        ->and($field->label)->toBe('fields.email')
        ->and($field->type)->toBe(ApiFieldType::STRING)
        ->and($field->sortable)->toBeTrue()
        ->and($field->filterable)->toBeTrue()
        ->and($field->searchable)->toBeTrue();
});

test('optional fields default to null or false', function () {
    $field = new ApiField(
        name: 'test',
        label: 'fields.test',
        type: ApiFieldType::STRING
    );

    expect($field->sortable)->toBeFalse()
        ->and($field->filterable)->toBeFalse()
        ->and($field->searchable)->toBeFalse()
        ->and($field->cast)->toBeNull()
        ->and($field->relationName)->toBeNull()
        ->and($field->relationField)->toBeNull()
        ->and($field->enumValues)->toBeNull()
        ->and($field->decimalPlaces)->toBeNull();
});
