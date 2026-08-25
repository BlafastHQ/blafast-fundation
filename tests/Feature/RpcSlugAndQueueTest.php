<?php

declare(strict_types=1);

use Blafast\Foundation\Jobs\ProcessDeferredApiRequest;
use Blafast\Foundation\Models\DeferredApiRequest;
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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Task 27 (M15/M7): the RPC method slug travels with the definition and the
 * queued-execution contract is honest.
 *
 * M15 — SalesOrderModel::apiMethods() is now the DOCUMENTED plain list; before
 * the normalizer, that shape yielded numeric keys everywhere: /meta advertised
 * `slug => 0`, permission sync created exec.{model}.0, and call/{slug} 404'd.
 * M7 — `->queued()` used to return HTTP 200 with a fabricated
 * `executed_at = now()`, no job id, no stored result; it now routes through the
 * deferred-request infrastructure (202 + poll link).
 */
beforeEach(function () {
    Schema::create('test_sales_orders', function ($table) {
        $table->uuid('id')->primary();
        $table->string('reference');
        $table->boolean('approved')->default(false);
        $table->timestamps();
    });

    Relation::morphMap(['sales-order-model' => SalesOrderModel::class]);

    app()->register(DynamicRouteServiceProvider::class);
    app(ModelRegistry::class)->register(SalesOrderModel::class);

    Route::prefix('api/v1')
        ->middleware('api')
        ->group(function () {
            Route::dynamicResource(SalesOrderModel::class);
        });
});

it('reaches a plain-list-declared method at call/{slug} and advertises real slugs in /meta', function () {
    actingAsSuperadmin();
    $order = SalesOrderModel::create(['reference' => 'SO-1']);

    // Reachable by its declared slug (pre-normalizer: 404, the key was 0).
    test()->postJson("/api/v1/sales-order-model/{$order->id}/call/approve")
        ->assertOk();
    expect($order->fresh()->approved)->toBeTrue();

    // /meta advertises every declared slug — never an array index.
    $meta = test()->getJson('/api/v1/meta/sales-order-model')->assertOk()->json();
    $slugs = collect(data_get($meta, 'data.attributes.methods', data_get($meta, 'data.methods', [])))
        ->pluck('slug');

    expect($slugs->all())->toContain('approve', 'print', 'notify', 'ship', 'echo-types', 'approve-later')
        ->and($slugs->filter(fn ($s) => is_numeric($s))->all())->toBe([]);
});

it('creates exec permissions per slug, never exec.{model}.{index}', function () {
    app(BlaFastPermissionRegistrar::class)->registerPermissionsForModel(SalesOrderModel::class);

    $names = Permission::query()->pluck('name');

    expect($names->contains('exec.sales-order-model.approve'))->toBeTrue()
        ->and($names->contains('exec.sales-order-model.approve-later'))->toBeTrue()
        ->and($names->contains('exec.sales-order-model.echo-types'))->toBeTrue()
        ->and($names->filter(fn ($n) => preg_match('/^exec\..*\.\d+$/', $n))->all())->toBe([]);

    // A method-level grant authorizes exactly that slug.
    $user = User::factory()->create();
    $user->givePermissionTo('exec.sales-order-model.print');

    $checker = app(ExecPermissionChecker::class);
    expect($checker->executableMethods($user, SalesOrderModel::class))->toBe(['print'])
        ->and($checker->canExecute($user, 'sales-order-model', 'print'))->toBeTrue()
        ->and($checker->canExecute($user, 'sales-order-model', 'ship'))->toBeFalse();
});

it('returns 202 with a trackable id for a queued method and the result is retrievable', function () {
    // Org-scoped grant so the request has a real organization context — the
    // deferred store requires one.
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->addUser($user, 'User');

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($org->id);
    Permission::findOrCreate('exec.sales-order-model', 'api');
    $role = Role::findOrCreate('Runner', 'api');
    $role->givePermissionTo('exec.sales-order-model');
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(null);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    $order = SalesOrderModel::create(['reference' => 'SO-1']);

    Queue::fake();

    $accepted = test()->actingAs($user, 'sanctum')
        ->postJson(
            "/api/v1/sales-order-model/{$order->id}/call/approve-later",
            ['data' => ['attributes' => ['copies' => 3]]],
            ['X-Organization-Id' => $org->id],
        )
        ->assertStatus(202)
        ->assertJsonPath('data.type', 'deferred-request');

    $deferredId = $accepted->json('data.id');
    expect($deferredId)->not->toBeNull()
        ->and($accepted->json('data.links.poll'))->toContain("deferred/{$deferredId}")
        // Dispatched, not executed: no fabricated result, no side effect yet.
        ->and($order->fresh()->reference)->toBe('SO-1');

    // Worker turn: the stored request replays in-process and the loop guard
    // makes the queued method execute synchronously this time.
    $deferred = DeferredApiRequest::withoutOrganizationScope()->findOrFail($deferredId);
    (new ProcessDeferredApiRequest($deferred))->handle();

    expect($order->fresh()->reference)->toBe('approved-x3');

    // The result is RETRIEVABLE at the poll endpoint — the old contract had no
    // id and no stored result at all.
    test()->actingAs($user, 'sanctum')
        ->getJson("/api/v1/deferred/{$deferredId}", ['X-Organization-Id' => $org->id])
        ->assertOk()
        ->assertJsonPath('data.attributes.status', 'completed')
        ->assertJsonPath('data.attributes.result_status_code', 200)
        ->assertJsonPath('data.attributes.result.data.attributes.result.copies', 3);
});
