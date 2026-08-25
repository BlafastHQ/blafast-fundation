<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Activity;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Spatie\Permission\PermissionRegistrar;

/**
 * Task 23 (M20/M13/M18): every shipped config key is consumed; the shadowed
 * Role property is gone; token expiration is honoured.
 */
it('has no unconsumed config key (the M20 sweep, automated)', function () {
    $config = config('blafast-fundation');
    $leaves = array_keys(Arr::dot($config));

    $src = collect(File::allFiles(__DIR__.'/../../src'))
        ->map(fn ($f) => $f->getContents())
        ->implode("\n");

    $unread = collect($leaves)->reject(function (string $leaf) use ($src) {
        // A leaf is consumed if the exact key or ANY ancestor path is read
        // (group reads like queue.names / media.conversions consume children).
        $parts = explode('.', $leaf);
        $probe = '';

        foreach ($parts as $part) {
            $probe = $probe === '' ? $part : "{$probe}.{$part}";

            if (str_contains($src, "blafast-fundation.{$probe}'")
                || str_contains($src, "blafast-fundation.{$probe}\"")) {
                return true;
            }
        }

        return false;
    })->values();

    // Deliberate forward-wired exception: media.queue_conversions (task 25) —
    // kept per the plan's cross-reference. (media.disk went live with task 24.)
    $allowed = ['media.queue_conversions'];

    expect($unread->diff($allowed)->all())->toBe([]);
});

it('computes isGlobal/isSuperadmin from the real attribute (M13)', function () {
    $org = Organization::factory()->create();

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($org->id);
    $orgSuperadmin = Role::create(['name' => 'Superadmin', 'guard_name' => 'api', 'organization_id' => $org->id]);
    $registrar->setPermissionsTeamId(null);

    // The old shadowing property made organization_id read as null — so an
    // org-scoped role merely NAMED Superadmin passed both checks.
    expect($orgSuperadmin->organization_id)->toBe($org->id)
        ->and($orgSuperadmin->isGlobal())->toBeFalse()
        ->and($orgSuperadmin->isSuperadmin())->toBeFalse();

    $global = Role::create(['name' => 'GlobalOne', 'guard_name' => 'api']);
    expect($global->isGlobal())->toBeTrue();
});

it('honours the configured token expiration on login (M18)', function () {
    config()->set('blafast-fundation.auth.token.expiration', 90);

    $user = User::factory()->create([
        'email' => 'expiry@blafast.io',
        'password' => bcrypt('secret-password'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'expiry@blafast.io',
        'password' => 'secret-password',
        'device_name' => 'test-device',
    ])->assertStatus(201);

    $expiresAt = $user->tokens()->first()->expires_at;
    expect($expiresAt)->not->toBeNull()
        ->and(now()->diffInMinutes($expiresAt))->toBeGreaterThan(85)
        ->and(now()->diffInMinutes($expiresAt))->toBeLessThan(95);
});

it('honours the configured organization header name', function () {
    config()->set('blafast-fundation.organization.header_name', 'X-Tenant');

    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->addUser($user, 'User');

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/test-can/anything', ['X-Tenant' => $org->id])
        ->assertOk();

    // The old hardcoded header no longer resolves anything.
    $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/test-can/anything', ['X-Organization-Id' => $org->id])
        ->assertStatus(400);
});

it('honours retention_days as the activity cleanup default', function () {
    config()->set('blafast-fundation.activity_log.retention_days', 30);

    Activity::withoutOrganizationScope()->create([
        'log_name' => 'default',
        'description' => 'mid-age entry',
        'created_at' => now()->subDays(60),
        'updated_at' => now()->subDays(60),
    ]);

    $this->artisan('blafast:activity:cleanup')->assertSuccessful();

    // 60 days old > 30-day configured retention → deleted (the old default 365
    // would have kept it).
    expect(Activity::withoutOrganizationScope()->count())->toBe(0);
});
