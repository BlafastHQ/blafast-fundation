<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Tests\Fixtures\TenantModel;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 20 (H24/M6): membership lifecycle + the forOrganization trap.
 */
it('re-adds a former member as an active membership instead of a 500 (H24)', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();

    $org->addUser($user, 'User');
    $org->removeUser($user);

    expect($org->hasUser($user))->toBeFalse();

    // The old attach() threw a unique-violation QueryException here.
    $org->addUser($user, 'Admin', ['invited_by' => 'test']);

    $pivot = DB::table('organization_user')
        ->where('organization_id', $org->id)
        ->where('user_id', $user->id)
        ->get();

    expect($pivot)->toHaveCount(1) // history preserved, no duplicate row
        ->and((bool) $pivot[0]->is_active)->toBeTrue()
        ->and($pivot[0]->left_at)->toBeNull()
        ->and($pivot[0]->role)->toBe('Admin')
        ->and($org->hasUser($user))->toBeTrue();
});

it('rejects an ex-member in OrganizationContext::set() and with()', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->addUser($user, 'User');
    $org->removeUser($user);

    $context = app(OrganizationContext::class);

    expect(fn () => $context->set($org, $user))->toThrow(RuntimeException::class);
    expect(fn () => $context->with($org, $user, fn () => null))->toThrow(RuntimeException::class);
});

it('returns cross-tenant rows via the static forOrganization and fails loudly on the removed scope style (M6)', function () {
    Schema::create('tenant_models', function ($table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
        $table->timestamps();
    });

    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $user = User::factory()->create();
    $orgA->addUser($user, 'User');

    TenantModel::withoutOrganizationScope()->create(['name' => 'a-row', 'organization_id' => $orgA->id]);
    TenantModel::withoutOrganizationScope()->create(['name' => 'b-row', 'organization_id' => $orgB->id]);

    // Cross-tenant query while a DIFFERENT org's context is active.
    app(OrganizationContext::class)->set($orgA, $user);

    expect(TenantModel::forOrganization($orgB->id)->pluck('name')->all())->toBe(['b-row']);

    // The shadowing scope variant used to return [] silently here; it is gone
    // and the fluent style fails loudly instead of lying.
    expect(fn () => TenantModel::query()->forOrganization($orgB->id)->get())
        ->toThrow(BadMethodCallException::class);

    app(OrganizationContext::class)->clear();
});
