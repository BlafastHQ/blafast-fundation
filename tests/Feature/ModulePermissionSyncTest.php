<?php

declare(strict_types=1);

use Blafast\Foundation\Dto\ModuleInfo;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Services\ModuleRegistry;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Support\Str;

/**
 * Task 8 (H8): module permission sync must use the CONFIGURED Permission model
 * (uuid keys), guard `api`, and match on the full identity (name + guard +
 * team) — the old code used the base spatie model (NULL uuid id + nonexistent
 * `description` column: Postgres-fatal) with guard `web` rows that could never
 * satisfy can().
 */
beforeEach(function () {
    $module = new ModuleInfo('blafast/billing', '1.0.0', [], 'Billing module', true);

    $this->mock(ModuleRegistry::class, function ($mock) use ($module) {
        $mock->shouldReceive('enabled')->andReturn(collect([$module]));
        $mock->shouldReceive('get')->with('blafast/billing')->andReturn($module);
    });

    config()->set('blafast-billing.permissions', [
        ['name' => 'view_billing_module'],
        ['name' => 'billing.reports', 'guard_name' => 'api'],
    ]);
});

it('syncs module permissions with uuid ids, guard api and a null team', function () {
    $this->artisan('blafast:permissions:sync', ['--force' => true])->assertSuccessful();

    $permission = Permission::where('name', 'view_billing_module')->first();

    expect($permission)->not->toBeNull()
        ->and(Str::isUuid((string) $permission->id))->toBeTrue()
        ->and($permission->guard_name)->toBe('api')
        ->and($permission->organization_id)->toBeNull()
        ->and(Permission::where('name', 'billing.reports')->exists())->toBeTrue();
});

it('is idempotent across reruns', function () {
    $this->artisan('blafast:permissions:sync', ['--force' => true])->assertSuccessful();
    $this->artisan('blafast:permissions:sync', ['--force' => true])->assertSuccessful();

    expect(Permission::where('name', 'view_billing_module')->count())->toBe(1)
        ->and(Permission::where('name', 'billing.reports')->count())->toBe(1);
});

it('gates a module: granted → allowed, revoked → denied', function () {
    $this->artisan('blafast:permissions:sync', ['--force' => true])->assertSuccessful();

    $user = User::factory()->create();
    $role = Role::create(['name' => 'BillingUser', 'guard_name' => 'api']);
    $role->givePermissionTo('view_billing_module');
    $user->assignRole($role);

    expect($user->can('view_billing_module'))->toBeTrue();

    $role->revokePermissionTo('view_billing_module');
    $user->unsetRelation('roles')->unsetRelation('permissions');

    expect($user->can('view_billing_module'))->toBeFalse();
});
