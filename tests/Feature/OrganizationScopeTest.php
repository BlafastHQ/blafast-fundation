<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Tests\Fixtures\TenantModel;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create class alias for User model if it doesn't exist
    if (! class_exists(\App\Models\User::class)) {
        class_alias(User::class, \App\Models\User::class);
    }

    // Create tenant_models table for testing
    Schema::create('tenant_models', function ($table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
        $table->timestamps();
    });
});

test('scope filters queries by organization when context is set', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $user = createTestUser();

    $org1->addUser($user, 'member');

    // Create records for each organization
    $record1 = TenantModel::withoutOrganizationScope()->create([
        'name' => 'Record 1',
        'organization_id' => $org1->id,
    ]);

    $record2 = TenantModel::withoutOrganizationScope()->create([
        'name' => 'Record 2',
        'organization_id' => $org2->id,
    ]);

    // Set organization context
    app(OrganizationContext::class)->set($org1, $user);

    // Query should only return org1 records
    $results = TenantModel::all();

    expect($results->count())->toBe(1)
        ->and($results->first()->id)->toBe($record1->id);
});

test('scope does not filter when no context is set', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();

    // Create records for each organization
    TenantModel::withoutOrganizationScope()->create([
        'name' => 'Record 1',
        'organization_id' => $org1->id,
    ]);

    TenantModel::withoutOrganizationScope()->create([
        'name' => 'Record 2',
        'organization_id' => $org2->id,
    ]);

    // No context set - should return all records
    $results = TenantModel::withoutOrganizationScope()->get();

    expect($results->count())->toBe(2);
});

test('scope does not filter when in global context mode', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $superadmin = createTestUser();

    // Create records for each organization
    TenantModel::withoutOrganizationScope()->create([
        'name' => 'Record 1',
        'organization_id' => $org1->id,
    ]);

    TenantModel::withoutOrganizationScope()->create([
        'name' => 'Record 2',
        'organization_id' => $org2->id,
    ]);

    // Set global context (superadmin mode)
    app(OrganizationContext::class)->setGlobalContext($superadmin);

    // Should return all records
    $results = TenantModel::all();

    expect($results->count())->toBe(2);
});

test('withoutOrganizationScope bypasses the filter', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $user = createTestUser();

    $org1->addUser($user, 'member');

    // Create records for each organization
    TenantModel::withoutOrganizationScope()->create([
        'name' => 'Record 1',
        'organization_id' => $org1->id,
    ]);

    TenantModel::withoutOrganizationScope()->create([
        'name' => 'Record 2',
        'organization_id' => $org2->id,
    ]);

    // Set organization context
    app(OrganizationContext::class)->set($org1, $user);

    // withoutOrganizationScope should return all records
    $results = TenantModel::withoutOrganizationScope()->get();

    expect($results->count())->toBe(2);
});

test('automatically assigns organization_id when creating records', function () {
    $org = Organization::factory()->create();
    $user = createTestUser();

    $org->addUser($user, 'member');

    // Set organization context
    app(OrganizationContext::class)->set($org, $user);

    // Create record without specifying organization_id
    $record = TenantModel::create([
        'name' => 'Test Record',
    ]);

    expect($record->organization_id)->toBe($org->id);
});

test('does not override manually set organization_id', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $user = createTestUser();

    $org1->addUser($user, 'member');

    // Set organization context to org1
    app(OrganizationContext::class)->set($org1, $user);

    // Create record with explicit organization_id for org2
    $record = TenantModel::withoutOrganizationScope()->create([
        'name' => 'Test Record',
        'organization_id' => $org2->id,
    ]);

    // Should keep the manually set organization_id
    expect($record->organization_id)->toBe($org2->id);
});

test('organization relationship works correctly', function () {
    $org = Organization::factory()->create();

    $record = TenantModel::withoutOrganizationScope()->create([
        'name' => 'Test Record',
        'organization_id' => $org->id,
    ]);

    expect($record->organization)->not->toBeNull()
        ->and($record->organization->id)->toBe($org->id);
});

test('forOrganization method queries specific organization', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();

    $record1 = TenantModel::withoutOrganizationScope()->create([
        'name' => 'Record 1',
        'organization_id' => $org1->id,
    ]);

    $record2 = TenantModel::withoutOrganizationScope()->create([
        'name' => 'Record 2',
        'organization_id' => $org2->id,
    ]);

    // Query for org1 specifically
    $results = TenantModel::forOrganization($org1->id)->get();

    expect($results->count())->toBe(1)
        ->and($results->first()->id)->toBe($record1->id);
});

test('scope includes table name in where clause to avoid ambiguity', function () {
    $org = Organization::factory()->create();
    $user = createTestUser();

    $org->addUser($user, 'member');

    app(OrganizationContext::class)->set($org, $user);

    // Get the SQL query
    $query = TenantModel::query()->toSql();

    // Should include table name prefix (with quotes)
    expect($query)->toContain('"tenant_models"."organization_id"');
});

test('clearing context removes the filter', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $user = createTestUser();

    $org1->addUser($user, 'member');

    TenantModel::withoutOrganizationScope()->create([
        'name' => 'Record 1',
        'organization_id' => $org1->id,
    ]);

    TenantModel::withoutOrganizationScope()->create([
        'name' => 'Record 2',
        'organization_id' => $org2->id,
    ]);

    // Set context
    app(OrganizationContext::class)->set($org1, $user);
    expect(TenantModel::count())->toBe(1);

    // Clear context
    app(OrganizationContext::class)->clear();

    // Should now see no records (no context means no filter, but also no automatic assignment)
    expect(TenantModel::withoutOrganizationScope()->count())->toBe(2);
});

test('throws exception when user does not belong to organization', function () {
    $org = Organization::factory()->create();
    $user = createTestUser();

    // User is not added to organization
    app(OrganizationContext::class)->set($org, $user);
})->throws(RuntimeException::class, 'does not belong to organization');

test('context validation method works correctly', function () {
    $org = Organization::factory()->create();
    $user = createTestUser();

    $context = app(OrganizationContext::class);

    // Initially false
    expect($context->validateUserBelongsToOrganization($user, $org))->toBeFalse();

    // Add user to organization
    $org->addUser($user, 'member');

    // Now true
    expect($context->validateUserBelongsToOrganization($user, $org))->toBeTrue();
});
