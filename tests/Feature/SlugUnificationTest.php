<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Providers\DynamicRouteServiceProvider;
use Blafast\Foundation\Services\BlaFastPermissionRegistrar;
use Blafast\Foundation\Services\ExecPermissionChecker;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Tests\Fixtures\SalesOrderModel;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Task 7 (H6/H7): one canonical slug (getApiSlug, kebab) across registrar,
 * checker, meta and routes — proven on a MULTI-WORD model whose snake and kebab
 * derivations differ — and granted permissions that actually authorize the
 * dynamic endpoints (no Gate::before(fn () => true) crutch anywhere).
 */
beforeEach(function () {
    Schema::create('test_sales_orders', function ($table) {
        $table->uuid('id')->primary();
        $table->string('reference');
        $table->boolean('approved')->default(false);
        $table->timestamps();
    });

    // The package enforces a morph map; activity logging on RPC execution needs
    // an entry for the fixture (same pattern as FilesEndpointTest's product).
    Relation::morphMap([
        'sales-order-model' => SalesOrderModel::class,
    ]);

    app()->register(DynamicRouteServiceProvider::class);
    app(ModelRegistry::class)->register(SalesOrderModel::class);

    Route::prefix('api/v1')
        ->middleware('api')
        ->group(function () {
            Route::dynamicResource(SalesOrderModel::class);
        });
});

it('derives one canonical kebab slug across registrar, checker and meta', function () {
    expect(SalesOrderModel::getApiSlug())->toBe('sales-order-model');

    app(BlaFastPermissionRegistrar::class)->registerPermissionsForModel(SalesOrderModel::class);

    $names = Permission::query()->pluck('name');
    expect($names->contains('list_sales-order-model'))->toBeTrue()
        ->and($names->contains('exec.sales-order-model'))->toBeTrue()
        ->and($names->filter(fn ($n) => str_contains($n, 'sales_order'))->all())->toBe([]);

    // The checker resolves the same slug: an exec grant under the canonical name works.
    $user = User::factory()->create();
    $user->givePermissionTo('exec.sales-order-model');

    expect(app(ExecPermissionChecker::class)->canExecute($user, SalesOrderModel::getApiSlug(), 'approve'))->toBeTrue();
});

it('lists a registered model with the list grant and 403s without it', function () {
    SalesOrderModel::create(['reference' => 'SO-1']);

    $granted = User::factory()->create();
    Permission::findOrCreate('list_sales-order-model', 'api');
    $granted->givePermissionTo('list_sales-order-model');

    // Tightened with task 10 (the C5/H12 filter-pipeline crash is fixed):
    // the granted user gets a real 200 with rows.
    $this->actingAs($granted, 'sanctum')
        ->getJson('/api/v1/sales-order-model')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $stranger = User::factory()->create();
    $this->actingAs($stranger, 'sanctum')
        ->getJson('/api/v1/sales-order-model')
        ->assertStatus(403);
});

it('authorizes the RPC call through defaultRights + permissions:sync for a multi-word model', function () {
    // Org lane first: spatie's role-name uniqueness spans global + team rows, so
    // the org-scoped Admin must exist before the global one.
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->addUser($user, 'User');

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($org->id);
    $role = Role::create(['name' => 'Admin', 'guard_name' => 'api', 'organization_id' => $org->id]);
    $registrar->setPermissionsTeamId(null);

    // The command drives the canonical path (global lane)…
    $this->artisan('blafast:permissions:sync', ['--models' => true, '--force' => true])
        ->assertSuccessful();

    expect(Permission::where('name', 'exec.sales-order-model')->exists())->toBeTrue();

    // …and the org lane goes through the same registrar the command calls
    // (the command has no --organization option; task 8 owns its gaps).
    $registrar->setPermissionsTeamId($org->id);
    app(BlaFastPermissionRegistrar::class)->syncAll($org->id);
    expect($role->fresh()->hasPermissionTo('exec.sales-order-model'))->toBeTrue();

    $user->assignRole($role);
    $registrar->setPermissionsTeamId(null);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    $order = SalesOrderModel::create(['reference' => 'SO-2']);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales-order-model/{$order->id}/call/approve", [], ['X-Organization-Id' => $org->id])
        ->assertOk();

    expect($order->fresh()->approved)->toBeTrue();

    // Revoking closes the door again.
    $registrar->setPermissionsTeamId($org->id);
    $role->revokePermissionTo('exec.sales-order-model');
    $registrar->setPermissionsTeamId(null);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/sales-order-model/{$order->id}/call/approve", [], ['X-Organization-Id' => $org->id])
        ->assertStatus(403);
});

it('renames legacy snake and plural permissions via blafast:permissions:migrate-slugs', function () {
    // Rows created under the old schemes.
    foreach (['exec.sales_order_model', 'exec.sales_order_model.approve', 'view_sales_order_model', 'list_organizations'] as $legacy) {
        Permission::findOrCreate($legacy, 'api');
    }
    // A target that already exists → the legacy row must be skipped, not clobbered.
    Permission::findOrCreate('view_sales-order-model', 'api');

    $this->artisan('blafast:permissions:migrate-slugs')->assertSuccessful();

    $names = Permission::query()->pluck('name');
    expect($names->contains('exec.sales-order-model'))->toBeTrue()
        ->and($names->contains('exec.sales-order-model.approve'))->toBeTrue()
        ->and($names->contains('list_organization'))->toBeTrue()
        ->and($names->contains('exec.sales_order_model'))->toBeFalse()
        ->and($names->contains('exec.sales_order_model.approve'))->toBeFalse()
        ->and($names->contains('list_organizations'))->toBeFalse()
        // skipped: both the legacy and pre-existing canonical rows survive
        ->and($names->contains('view_sales_order_model'))->toBeTrue()
        ->and($names->contains('view_sales-order-model'))->toBeTrue();
});
