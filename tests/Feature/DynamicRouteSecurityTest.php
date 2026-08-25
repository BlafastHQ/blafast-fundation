<?php

declare(strict_types=1);

use Blafast\Foundation\Database\Seeders\CountrySeeder;
use Blafast\Foundation\Database\Seeders\CurrencySeeder;
use Blafast\Foundation\Database\Seeders\PermissionSeeder;
use Blafast\Foundation\Database\Seeders\RoleSeeder;
use Blafast\Foundation\Database\Seeders\SystemSettingsSeeder;
use Blafast\Foundation\Jobs\Middleware\RestoreOrganizationContext;
use Blafast\Foundation\Models\Activity;
use Blafast\Foundation\Models\Country;
use Blafast\Foundation\Models\Currency;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Models\SystemSetting;
use Blafast\Foundation\Providers\DynamicRouteServiceProvider;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Tests\Fixtures\TenantModel;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Task 12 (H1/L4 + the fail-closed decision): secure-by-default macro routes and
 * an OrganizationScope that never silently serves a full table.
 */
beforeEach(function () {
    Schema::create('tenant_models', function ($table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
        $table->timestamps();
    });

    app()->register(DynamicRouteServiceProvider::class);
});

function tenantRow(Organization $org, string $name): TenantModel
{
    return TenantModel::withoutOrganizationScope()->create([
        'name' => $name,
        'organization_id' => $org->id,
    ]);
}

it('is not reachable unauthenticated and serves only the current org rows (H1)', function () {
    Route::prefix('api/v1')->middleware('api')->group(function () {
        Route::dynamicResource(TenantModel::class); // NO options — the old default leaked
    });

    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    tenantRow($orgA, 'A row');
    tenantRow($orgB, 'B row');

    // Unauthenticated: 401, not a full cross-tenant table.
    $this->getJson('/api/v1/tenant-model')->assertStatus(401);

    // Authenticated org-A member with an org-scoped grant: ONLY org A's rows.
    $user = User::factory()->create();
    $orgA->addUser($user, 'User');
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($orgA->id);
    Permission::findOrCreate('list_tenant-model', 'api');
    $role = Role::findOrCreate('TenantReader', 'api');
    $role->givePermissionTo('list_tenant-model');
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(null);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/tenant-model', ['X-Organization-Id' => $orgA->id])
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.name', 'A row');
});

it('appends caller middleware to the defaults instead of replacing them', function () {
    Route::prefix('api/v1')->middleware('api')->group(function () {
        Route::dynamicResource(TenantModel::class, ['middleware' => ['throttle:extra']]);
    });

    $route = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === 'tenant-model.index');

    expect($route)->not->toBeNull();
    $middleware = $route->gatherMiddleware();
    expect($middleware)->toContain('auth:sanctum')
        ->and($middleware)->toContain('throttle:api')
        ->and($middleware)->toContain('org.resolve')
        ->and($middleware)->toContain('throttle:extra');
});

it('exposes no meta route from the macro under any prefix (L4)', function () {
    Route::prefix('internal-api')->middleware('api')->group(function () {
        Route::dynamicResource(TenantModel::class);
    });

    expect(collect(Route::getRoutes())->first(fn ($r) => $r->getName() === 'tenant-model.meta'))->toBeNull();

    $this->getJson('/internal-api/meta/tenant-model')->assertStatus(404);
});

it('fails closed with no context in the console/test runtime', function () {
    $org = Organization::factory()->create();
    tenantRow($org, 'invisible');

    // No context (this test process is the CLI runtime): zero rows, not all rows.
    expect(TenantModel::count())->toBe(0)
        ->and(TenantModel::withoutOrganizationScope()->count())->toBe(1);
});

it('fails closed inside a queued job with no organization id', function () {
    $org = Organization::factory()->create();
    tenantRow($org, 'invisible');

    $observed = null;
    (new RestoreOrganizationContext(null))->handle(new stdClass, function () use (&$observed) {
        $observed = TenantModel::count();
    });

    expect($observed)->toBe(0);
});

it('fails closed over HTTP on a route without org.resolve', function () {
    Route::get('/bare-tenants', fn () => TenantModel::pluck('name'))->middleware('api');

    $org = Organization::factory()->create();
    tenantRow($org, 'invisible');

    $this->getJson('/bare-tenants')->assertOk()->assertExactJson([]);
});

it('still returns every org through global context and runAsSystem', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    tenantRow($orgA, 'a');
    tenantRow($orgB, 'b');

    $context = app(OrganizationContext::class);

    $context->setGlobalContext(); // user-less system entry point
    expect(TenantModel::count())->toBe(2);
    $context->clear();

    expect(TenantModel::count())->toBe(0)
        ->and($context->runAsSystem(fn () => TenantModel::count()))->toBe(2)
        ->and(TenantModel::count())->toBe(0); // previous (empty) context restored
});

it('keeps CleanupActivityLogCommand reading cross-org rows', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    foreach ([$orgA, $orgB] as $org) {
        Activity::withoutOrganizationScope()->create([
            'log_name' => 'default',
            'description' => 'old entry',
            'organization_id' => $org->id,
            'created_at' => now()->subDays(400),
            'updated_at' => now()->subDays(400),
        ]);
    }

    $this->artisan('blafast:activity:cleanup', ['--days' => 365])->assertSuccessful();

    expect(Activity::withoutOrganizationScope()->count())->toBe(0);
});

it('runs every package seeder without silently returning nothing (fail-closed audit)', function () {
    // Two orgs pre-exist; none of the seeders touch org-scoped models, so all
    // must complete and produce rows under the fail-closed scope.
    Organization::factory()->count(2)->create();

    foreach ([
        RoleSeeder::class,
        PermissionSeeder::class,
        CurrencySeeder::class,
        CountrySeeder::class,
        SystemSettingsSeeder::class,
    ] as $seeder) {
        (new $seeder)->run();
    }

    expect(Role::count())->toBeGreaterThan(0)
        ->and(Permission::count())->toBeGreaterThan(0)
        ->and(Currency::count())->toBeGreaterThan(0)
        ->and(Country::count())->toBeGreaterThan(0)
        ->and(SystemSetting::count())->toBeGreaterThan(0);
});
