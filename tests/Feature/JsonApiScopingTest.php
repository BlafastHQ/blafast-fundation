<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Providers\DynamicRouteServiceProvider;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

/**
 * Task 22 (M5): the JSON:API renderable is scoped to the PACKAGE's routes by
 * default — installing the package must not rewrite the host's error contract.
 */
it('keeps Laravel\'s standard error shape on host routes', function () {
    Route::get('/host/boom', function () {
        abort(404, 'host says no');
    });

    $body = $this->getJson('/host/boom')->assertStatus(404)->json();

    // Laravel's default shape — NOT the package's {errors:[{status,code,…}]}.
    expect($body)->toHaveKey('message')
        ->and($body)->not->toHaveKey('errors');
});

it('keeps the field-keyed validation map on host routes', function () {
    Route::post('/host/validate', function () {
        Validator::make(request()->all(), ['email' => 'required|email'])->validate();
    });

    $body = $this->postJson('/host/validate', [])->assertStatus(422)->json();

    expect($body['errors'])->toHaveKey('email') // Laravel's {errors:{field:[…]}}
        ->and($body)->toHaveKey('message');
});

it('still renders the JSON:API shape on package routes', function () {
    app()->register(DynamicRouteServiceProvider::class);
    app(ModelRegistry::class)->register(Organization::class);
    Route::prefix('api/v1')->middleware('api')->group(function () {
        Route::dynamicResource(Organization::class);
    });

    $viewer = User::factory()->create();
    Permission::findOrCreate('list_organization', 'api');
    $viewer->givePermissionTo('list_organization');
    Role::findOrCreate('Superadmin', 'api');
    $viewer->assignRole('Superadmin');
    $viewer->unsetRelation('roles')->unsetRelation('permissions');

    // Unknown include on a PACKAGE controller route → the package's error shape.
    $body = $this->actingAs($viewer, 'sanctum')
        ->getJson('/api/v1/organization?include=bogus')
        ->assertStatus(400)
        ->json();

    expect($body['errors'][0])->toHaveKeys(['status', 'code', 'title']);
});
