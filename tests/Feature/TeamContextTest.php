<?php

declare(strict_types=1);

use Blafast\Foundation\Jobs\Middleware\RestoreOrganizationContext;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Permission;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Tests\Fixtures\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Task 5 (C1): spatie runs in teams mode, so the registrar's team id MUST follow
 * the organization context — no runtime code ever set it before, which made every
 * org-scoped grant resolve against team NULL and deny all members.
 */
function teamId(): mixed
{
    return app(PermissionRegistrar::class)->getPermissionsTeamId();
}

it('sets and resets the registrar team id on every context transition', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->addUser($user, 'User');
    $context = app(OrganizationContext::class);

    expect(teamId())->toBeNull();

    $context->set($org, $user);
    expect(teamId())->toBe($org->id);

    $context->setGlobalContext($user);
    expect(teamId())->toBeNull();

    $context->set($org, $user);
    $context->clear();
    expect(teamId())->toBeNull();
});

it('restores the previous team id after with() and withGlobalContext()', function () {
    $user = User::factory()->create();
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $orgA->addUser($user, 'User');
    $orgB->addUser($user, 'User');
    $context = app(OrganizationContext::class);

    $context->set($orgA, $user);

    $context->with($orgB, $user, function () use ($orgB) {
        expect(teamId())->toBe($orgB->id);
    });
    expect(teamId())->toBe($orgA->id);

    $context->withGlobalContext($user, function () {
        expect(teamId())->toBeNull();
    });
    expect(teamId())->toBe($orgA->id);
});

it('grants an org-scoped permission in its org and denies it in another — through the middleware stack', function () {
    $user = User::factory()->create();
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $orgA->addUser($user, 'User');
    $orgB->addUser($user, 'User');

    $registrar = app(PermissionRegistrar::class);

    // Grant `edit-things` via an org-A-scoped role (data setup needs the team id).
    $registrar->setPermissionsTeamId($orgA->id);
    Permission::create(['name' => 'edit-things', 'guard_name' => 'api', 'organization_id' => $orgA->id]);
    $role = Role::create(['name' => 'Editor', 'guard_name' => 'api', 'organization_id' => $orgA->id]);
    $role->givePermissionTo('edit-things');
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(null);
    $user->unsetRelation('roles')->unsetRelation('permissions');

    $this->actingAs($user, 'sanctum');

    $this->getJson('/api/v1/test-can/edit-things', ['X-Organization-Id' => $orgA->id])
        ->assertOk()
        ->assertJson(['can' => true]);

    $this->getJson('/api/v1/test-can/edit-things', ['X-Organization-Id' => $orgB->id])
        ->assertOk()
        ->assertJson(['can' => false]);
});

it('returns 400 MISSING_ORGANIZATION on a stateless request without the header — not a 500', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum');

    $this->getJson('/api/v1/test-can/anything')
        ->assertStatus(400)
        ->assertJsonPath('errors.0.code', 'MISSING_ORGANIZATION');
});

it('restores the team id inside a job and does not leak it to the next job', function () {
    $user = User::factory()->create();
    $org = Organization::factory()->create();
    $org->addUser($user, 'User');

    $observed = [];
    $job = new stdClass;

    (new RestoreOrganizationContext($org->id))->handle($job, function () use (&$observed) {
        $observed['inside_scoped'] = teamId();
    });
    $observed['after_scoped'] = teamId();

    (new RestoreOrganizationContext(null))->handle($job, function () use (&$observed) {
        $observed['inside_unscoped'] = teamId();
    });

    expect($observed['inside_scoped'])->toBe($org->id)
        ->and($observed['after_scoped'])->toBeNull()
        ->and($observed['inside_unscoped'])->toBeNull();
});
