<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create class alias for User model if it doesn't exist
    if (! class_exists(\App\Models\User::class)) {
        class_alias(User::class, \App\Models\User::class);
    }
});

test('context starts empty', function () {
    $context = app(OrganizationContext::class);

    expect($context->hasContext())->toBeFalse()
        ->and($context->isGlobalContext())->toBeFalse()
        ->and($context->id())->toBeNull()
        ->and($context->slug())->toBeNull()
        ->and($context->organization())->toBeNull()
        ->and($context->user())->toBeNull();
});

test('can set organization context', function () {
    $org = Organization::factory()->create();
    $user = createTestUser();
    $org->addUser($user, 'member');

    $context = app(OrganizationContext::class);
    $context->set($org, $user);

    expect($context->hasContext())->toBeTrue()
        ->and($context->isGlobalContext())->toBeFalse()
        ->and($context->id())->toBe($org->id)
        ->and($context->slug())->toBe($org->slug)
        ->and($context->organization()->id)->toBe($org->id)
        ->and($context->user()->id)->toBe($user->id);
});

test('can set global context', function () {
    $superadmin = createTestUser();

    $context = app(OrganizationContext::class);
    $context->setGlobalContext($superadmin);

    expect($context->hasContext())->toBeFalse()
        ->and($context->isGlobalContext())->toBeTrue()
        ->and($context->organization())->toBeNull()
        ->and($context->user()->id)->toBe($superadmin->id);
});

test('can clear context', function () {
    $org = Organization::factory()->create();
    $user = createTestUser();
    $org->addUser($user, 'member');

    $context = app(OrganizationContext::class);
    $context->set($org, $user);

    expect($context->hasContext())->toBeTrue();

    $context->clear();

    expect($context->hasContext())->toBeFalse()
        ->and($context->isGlobalContext())->toBeFalse()
        ->and($context->organization())->toBeNull()
        ->and($context->user())->toBeNull();
});

test('cacheTag returns correct format', function () {
    $org = Organization::factory()->create();
    $user = createTestUser();
    $org->addUser($user, 'member');

    $context = app(OrganizationContext::class);
    $context->set($org, $user);

    expect($context->cacheTag())->toBe("organization:{$org->id}");
});

test('cacheTags returns array with id and slug tags', function () {
    $org = Organization::factory()->create();
    $user = createTestUser();
    $org->addUser($user, 'member');

    $context = app(OrganizationContext::class);
    $context->set($org, $user);

    $tags = $context->cacheTags();

    expect($tags)->toBeArray()
        ->and($tags)->toHaveCount(2)
        ->and($tags)->toContain("organization:{$org->id}")
        ->and($tags)->toContain("organization-slug:{$org->slug}");
});

test('cacheTag throws exception when no context is set', function () {
    $context = app(OrganizationContext::class);

    $context->cacheTag();
})->throws(RuntimeException::class, 'No organization context is set');

test('cacheTags throws exception when no context is set', function () {
    $context = app(OrganizationContext::class);

    $context->cacheTags();
})->throws(RuntimeException::class, 'No organization context is set');

test('require throws exception when no context is set', function () {
    $context = app(OrganizationContext::class);

    $context->require();
})->throws(RuntimeException::class, 'No organization context is set');

test('require returns organization when context is set', function () {
    $org = Organization::factory()->create();
    $user = createTestUser();
    $org->addUser($user, 'member');

    $context = app(OrganizationContext::class);
    $context->set($org, $user);

    $result = $context->require();

    expect($result)->toBeInstanceOf(Organization::class)
        ->and($result->id)->toBe($org->id);
});

test('with executes callback with temporary context', function () {
    $org1 = Organization::factory()->create();
    $org2 = Organization::factory()->create();
    $user = createTestUser();

    $org1->addUser($user, 'member');
    $org2->addUser($user, 'member');

    $context = app(OrganizationContext::class);
    $context->set($org1, $user);

    expect($context->id())->toBe($org1->id);

    $result = $context->with($org2, $user, function () use ($context, $org2) {
        expect($context->id())->toBe($org2->id);

        return 'success';
    });

    // Context should be restored
    expect($context->id())->toBe($org1->id)
        ->and($result)->toBe('success');
});

test('withGlobalContext executes callback with temporary global context', function () {
    $org = Organization::factory()->create();
    $user = createTestUser();
    $superadmin = createTestUser();

    $org->addUser($user, 'member');

    $context = app(OrganizationContext::class);
    $context->set($org, $user);

    expect($context->isGlobalContext())->toBeFalse();

    $result = $context->withGlobalContext($superadmin, function () use ($context) {
        expect($context->isGlobalContext())->toBeTrue();

        return 'success';
    });

    // Context should be restored
    expect($context->isGlobalContext())->toBeFalse()
        ->and($context->id())->toBe($org->id)
        ->and($result)->toBe('success');
});

test('validates user belongs to organization', function () {
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

test('throws exception when setting context with user who does not belong to organization', function () {
    $org = Organization::factory()->create();
    $user = createTestUser();

    $context = app(OrganizationContext::class);

    $context->set($org, $user);
})->throws(RuntimeException::class, 'does not belong to organization');

test('helper functions work correctly', function () {
    $org = Organization::factory()->create();
    $user = createTestUser();
    $org->addUser($user, 'member');

    $context = app(OrganizationContext::class);
    $context->set($org, $user);

    expect(organization())->toBeInstanceOf(Organization::class)
        ->and(organization()->id)->toBe($org->id)
        ->and(organization_id())->toBe($org->id)
        ->and(organization_slug())->toBe($org->slug)
        ->and(has_organization_context())->toBeTrue()
        ->and(is_global_organization_context())->toBeFalse()
        ->and(organization_context())->toBeInstanceOf(OrganizationContext::class);
});

test('helper functions return null when no context', function () {
    $context = app(OrganizationContext::class);
    $context->clear();

    expect(organization())->toBeNull()
        ->and(organization_id())->toBeNull()
        ->and(organization_slug())->toBeNull()
        ->and(has_organization_context())->toBeFalse()
        ->and(is_global_organization_context())->toBeFalse();
});
