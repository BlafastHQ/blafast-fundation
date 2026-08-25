<?php

declare(strict_types=1);

use Blafast\Foundation\Models\DeferredApiRequest;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Task 3 (C6): the model_has_* pivots must accept a NULL-team row (the global
 * Superadmin grant) AND org-scoped rows for the same role — the shipped composite
 * primary key made the former impossible on Postgres (PK columns are NOT NULL).
 * These tests are only meaningful on the pgsql lane; sqlite tolerated the old
 * schema, which is exactly how it shipped broken.
 */
it('assigns the global Superadmin role with no team id set', function () {
    $user = User::factory()->create();
    Role::create(['name' => 'Superadmin', 'guard_name' => 'api']);

    $user->assignRole('Superadmin');

    expect($user->fresh()->hasRole('Superadmin'))->toBeTrue();

    $pivot = DB::table('model_has_roles')->where('model_uuid', $user->id)->first();
    expect($pivot)->not->toBeNull()
        ->and($pivot->organization_id)->toBeNull();
});

it('grants the same role name to one user in several organizations', function () {
    $user = User::factory()->create();
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    Role::create(['name' => 'Admin', 'guard_name' => 'api', 'organization_id' => $orgA->id]);
    Role::create(['name' => 'Admin', 'guard_name' => 'api', 'organization_id' => $orgB->id]);

    $registrar = app(PermissionRegistrar::class);

    $registrar->setPermissionsTeamId($orgA->id);
    $user->assignRole('Admin');

    $registrar->setPermissionsTeamId($orgB->id);
    $user->unsetRelation('roles');
    $user->assignRole('Admin');

    $registrar->setPermissionsTeamId(null);

    $orgs = DB::table('model_has_roles')
        ->where('model_uuid', $user->id)
        ->pluck('organization_id');

    expect($orgs)->toHaveCount(2)
        ->and($orgs->sort()->values()->all())
        ->toBe(collect([$orgA->id, $orgB->id])->sort()->values()->all());
});

/**
 * Task 3 (C7): payload/headers/result carry `encrypted:json` casts — the encrypted
 * value is an opaque base64 string that Postgres's json column type rejected
 * (SQLSTATE 22P02). The columns are text now; the round-trip must be identity.
 */
it('round-trips an encrypted deferred api request on the real column types', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();

    $request = DeferredApiRequest::create([
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'http_method' => 'POST',
        'endpoint' => '/api/v1/test/echo',
        'payload' => ['answer' => 42, 'nested' => ['a' => 'b']],
        'query_params' => ['page' => 1],
        'headers' => ['accept' => ['application/json']],
        'result' => ['ok' => true],
        'expires_at' => now()->addDay(),
    ]);

    $fresh = $request->fresh();

    expect($fresh->payload)->toBe(['answer' => 42, 'nested' => ['a' => 'b']])
        ->and($fresh->headers)->toBe(['accept' => ['application/json']])
        ->and($fresh->result)->toBe(['ok' => true])
        // and the stored value really is the encrypted blob, not plaintext json
        ->and((string) DB::table('deferred_api_requests')->where('id', $request->id)->value('payload'))
        ->not->toContain('answer');
});
