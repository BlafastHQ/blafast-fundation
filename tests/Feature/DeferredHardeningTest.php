<?php

declare(strict_types=1);

use Blafast\Foundation\Enums\DeferredRequestStatus;
use Blafast\Foundation\Models\DeferredApiRequest;
use Blafast\Foundation\Models\DeferredEndpointConfig;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;

/**
 * Task 16 (M8–M11): hardening around the now-working deferred subsystem.
 */
function deferredSetup(bool $force = false): array
{
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->addUser($user, 'User');

    DeferredEndpointConfig::withoutOrganizationScope()->create([
        'organization_id' => null,
        'http_method' => 'POST',
        'endpoint_pattern' => 'api/v1/test-deferred/*',
        'is_active' => true,
        'force_deferred' => $force,
        'priority' => 'default',
    ]);

    Queue::fake();

    return [$org, $user];
}

it('ignores a spoofed execution marker: force_deferred still defers (M8)', function () {
    [$org, $user] = deferredSetup(force: true);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/test-deferred/echo', ['data' => []], [
            'X-Organization-Id' => $org->id,
            'X-Deferred-Execution' => 'true', // client-forged marker
        ])
        ->assertStatus(202);

    expect(DeferredApiRequest::withoutOrganizationScope()->count())->toBe(1);
});

it('keeps pending rows and removes only terminal rows past the completion TTL (M9)', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();

    $base = [
        'organization_id' => $org->id,
        'user_id' => $user->id,
        'http_method' => 'POST',
        'endpoint' => '/api/v1/test-deferred/echo',
        'headers' => [],
        'expires_at' => now()->subDays(30), // long past creation-anchored expiry
    ];

    $pending = DeferredApiRequest::withoutOrganizationScope()->create(
        $base + ['status' => DeferredRequestStatus::Pending, 'created_at' => now()->subDays(30)]
    );
    $processing = DeferredApiRequest::withoutOrganizationScope()->create(
        $base + ['status' => DeferredRequestStatus::Processing, 'created_at' => now()->subDays(30)]
    );
    $oldCompleted = DeferredApiRequest::withoutOrganizationScope()->create(
        $base + ['status' => DeferredRequestStatus::Completed, 'completed_at' => now()->subDays(10)]
    );
    $freshCompleted = DeferredApiRequest::withoutOrganizationScope()->create(
        $base + ['status' => DeferredRequestStatus::Completed, 'completed_at' => now()->subHours(2)]
    );

    $this->artisan('blafast:deferred:cleanup', ['--days' => 7])->assertSuccessful();

    $remaining = DeferredApiRequest::withoutOrganizationScope()->pluck('id');
    expect($remaining)->toContain($pending->id)
        ->and($remaining)->toContain($processing->id)
        ->and($remaining)->toContain($freshCompleted->id)
        ->and($remaining)->not->toContain($oldCompleted->id);
});

it('rejects deferring a multipart upload clearly (M10)', function () {
    [$org, $user] = deferredSetup();

    $response = $this->actingAs($user, 'sanctum')
        ->post('/api/v1/test-deferred/echo', [
            'data' => ['x' => 1],
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ], [
            'X-Organization-Id' => $org->id,
            'X-Blafast-Defer' => 'true',
            'Accept' => 'application/json',
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'DEFERRED_FILES_UNSUPPORTED');

    expect(DeferredApiRequest::withoutOrganizationScope()->count())->toBe(0);
});

it('degrades to synchronous execution for a superadmin in global context (M11)', function () {
    deferredSetup();

    $superadmin = User::factory()->create();
    Role::findOrCreate('Superadmin', 'api');
    $superadmin->assignRole('Superadmin');
    $superadmin->unsetRelation('roles')->unsetRelation('permissions');

    // No org header → global context → synchronous 200 (not a NOT NULL 500).
    $this->actingAs($superadmin, 'sanctum')
        ->postJson('/api/v1/test-deferred/echo', ['data' => ['k' => 1]], [
            'X-Blafast-Defer' => 'true',
        ])
        ->assertOk()
        ->assertJsonPath('payload.k', 1);

    expect(DeferredApiRequest::withoutOrganizationScope()->count())->toBe(0);
});

it('honours the configurable defer header name and the kill switch', function () {
    [$org, $user] = deferredSetup();

    config()->set('blafast-fundation.deferred.header_name', 'X-Custom-Defer');

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/test-deferred/echo', ['data' => []], [
            'X-Organization-Id' => $org->id,
            'X-Custom-Defer' => 'true',
        ])
        ->assertStatus(202);

    config()->set('blafast-fundation.deferred.enabled', false);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/test-deferred/echo', ['data' => []], [
            'X-Organization-Id' => $org->id,
            'X-Custom-Defer' => 'true',
        ])
        ->assertOk();
});
