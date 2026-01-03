<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\ModelRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->registry = new ModelRegistry;
});

test('registry can register a model', function () {
    $this->registry->register(Organization::class);

    expect($this->registry->has('organization'))->toBeTrue();
});

test('registry throws exception when registering non-api-structure model', function () {
    $this->registry->register(Model::class);
})->throws(\InvalidArgumentException::class, 'must implement HasApiStructure');

test('registry can get a registered model', function () {
    $this->registry->register(Organization::class);

    expect($this->registry->get('organization'))->toBe(Organization::class);
});

test('registry returns null for unregistered model', function () {
    expect($this->registry->get('nonexistent'))->toBeNull();
});

test('registry can check if model is registered', function () {
    $this->registry->register(Organization::class);

    expect($this->registry->has('organization'))->toBeTrue()
        ->and($this->registry->has('nonexistent'))->toBeFalse();
});

test('registry can get all registered models', function () {
    $this->registry->register(Organization::class);

    $all = $this->registry->all();

    expect($all)->toBeArray()
        ->and($all)->toHaveKey('organization')
        ->and($all['organization'])->toBe(Organization::class);
});

test('registry can get all slugs', function () {
    $this->registry->register(Organization::class);

    $slugs = $this->registry->slugs();

    expect($slugs)->toBeArray()
        ->and($slugs)->toContain('organization');
});

test('registry can resolve a model', function () {
    $this->registry->register(Organization::class);

    expect($this->registry->resolve('organization'))->toBe(Organization::class);
});

test('registry throws exception when resolving unregistered model', function () {
    $this->registry->resolve('nonexistent');
})->throws(ModelNotFoundException::class, 'Model not found for slug: nonexistent');

test('registry can clear all models', function () {
    $this->registry->register(Organization::class);

    expect($this->registry->has('organization'))->toBeTrue();

    $this->registry->clear();

    expect($this->registry->has('organization'))->toBeFalse();
});

test('registry prevents duplicate registration with same slug', function () {
    $this->registry->register(Organization::class);
    $this->registry->register(Organization::class);

    // Should not throw exception, just overwrite
    expect($this->registry->has('organization'))->toBeTrue();
});
