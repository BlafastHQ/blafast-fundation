<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Providers\DynamicRouteServiceProvider;
use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Tests\Fixtures\ProductModel;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Task 10 (C5/H12/H14/M12): explicit regressions for the list-query pipeline.
 * C5 shipped because no test ever EXECUTED a query through the endpoint.
 */
beforeEach(function () {
    Schema::create('test_products', function ($table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->text('description')->nullable();
        $table->decimal('price', 10, 2);
        $table->boolean('is_active')->default(true);
        $table->timestamps();
    });

    app()->register(DynamicRouteServiceProvider::class);
    app(ModelRegistry::class)->register(ProductModel::class);
    app(ModelRegistry::class)->register(Organization::class);

    Route::prefix('api/v1')
        ->middleware('api')
        ->group(function () {
            Route::dynamicResource(ProductModel::class);
            Route::dynamicResource(Organization::class);
        });

    $user = User::factory()->create();
    foreach (['list_product', 'list_organization'] as $name) {
        Permission::findOrCreate($name, 'api');
    }
    $user->givePermissionTo(['list_product', 'list_organization']);
    // Task 12: macro routes now carry org.resolve — superadmin ⇒ global context.
    Role::findOrCreate('Superadmin', 'api');
    $user->assignRole('Superadmin');
    $user->unsetRelation('roles')->unsetRelation('permissions');
    test()->actingAs($user, 'sanctum');
});

it('returns 200 with real rows through the endpoint (C5)', function () {
    ProductModel::factory()->count(3)->create();

    $this->getJson('/api/v1/product')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('matches a partial filter with no conflicting exact predicate in the SQL (H12)', function () {
    Organization::factory()->create(['name' => 'Acme Corporation']);
    Organization::factory()->create(['name' => 'Globex']);

    $captured = [];
    DB::listen(function ($query) use (&$captured) {
        if (str_contains($query->sql, 'organizations') && str_contains($query->sql, 'name')) {
            $captured[] = $query->sql;
        }
    });

    $this->getJson('/api/v1/organization?filter[name]=Acme')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.attributes.name', 'Acme Corporation');

    $filterSql = collect($captured)->first(fn ($sql) => str_contains(strtolower($sql), 'like'));
    expect($filterSql)->not->toBeNull()
        // exactly one predicate on name: the partial LIKE — no stacked `"name" = ?`
        ->and(preg_match('/"name"\s*=\s*\?/', $filterSql))->toBe(0);
});

it('reaches all 25 rows sharing a sort value by walking links.next (H14)', function () {
    ProductModel::factory()->count(25)->create(['name' => 'Same Name']);

    $seen = [];
    $url = '/api/v1/product?sort=name&page[per_page]=10';

    for ($hops = 0; $url !== null && $hops < 10; $hops++) {
        $response = $this->getJson($url)->assertOk();
        foreach ($response->json('data') as $row) {
            $seen[$row['id']] = true;
        }
        $next = $response->json('links.next');
        $url = $next ? (parse_url($next, PHP_URL_PATH).'?'.parse_url($next, PHP_URL_QUERY)) : null;
    }

    expect(count($seen))->toBe(25);
});

it('serves the model-declared pagination default when per_page is absent (M12)', function () {
    // ProductModel declares pagination.default_size = 15 (global default is 25).
    ProductModel::factory()->count(20)->create();

    $this->getJson('/api/v1/product')
        ->assertOk()
        ->assertJsonCount(15, 'data')
        ->assertJsonPath('meta.page.per_page', 15);
});
