<?php

declare(strict_types=1);

use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\SystemSetting;
use Blafast\Foundation\Services\MetadataCacheService;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Services\SettingsService;
use Blafast\Foundation\Tests\Fixtures\RecordSettingsJob;
use Blafast\Foundation\Tests\Fixtures\User;

/**
 * Task 19 (H23): singletons captured the per-request OrganizationContext in
 * their constructors — from the second job onward in a long-lived worker they
 * held a CLEARED context, so org settings fell back to system defaults and
 * cache keys were computed as `global`.
 */
it('resolves each org\'s own settings and cache keys across two worker jobs (H23)', function () {
    config()->set('queue.default', 'database');

    SystemSetting::create(['key' => 'probe.key', 'value' => 'SYS', 'type' => 'string', 'is_public' => true]);

    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $orgA->setSetting('probe.key', 'A-value')->save();
    $orgB->setSetting('probe.key', 'B-value')->save();

    $user = User::factory()->create();
    $orgA->addUser($user, 'User');
    $orgB->addUser($user, 'User');

    $context = app(OrganizationContext::class);

    $context->set($orgA->fresh(), $user);
    RecordSettingsJob::dispatch('a');
    $context->set($orgB->fresh(), $user);
    RecordSettingsJob::dispatch('b');
    $context->clear();

    // ONE long-lived process (this one) works both jobs — the H23 shape.
    $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertSuccessful();
    $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertSuccessful();

    $a = cache()->get('record-settings-a');
    $b = cache()->get('record-settings-b');

    expect($a['setting'])->toBe('A-value')
        ->and($a['cache_key'])->toContain($orgA->id)
        ->and($b['setting'])->toBe('B-value') // the old bug: 'SYS' here
        ->and($b['cache_key'])->toContain($orgB->id) // the old bug: ':global:'
        ->and($b['cache_key'])->not->toContain('global');
});

it('returns a live context after a scope flush (unit)', function () {
    $before = app(SettingsService::class);

    app()->forgetScopedInstances();

    $after = app(SettingsService::class);
    $liveContext = app(OrganizationContext::class);

    // A fresh scoped instance wrapping the LIVE context — not the stale one.
    expect($after)->not->toBe($before);

    $orgProbe = new ReflectionProperty($after, 'context');
    expect($orgProbe->getValue($after))->toBe($liveContext);

    $cacheService = app(MetadataCacheService::class);
    $cacheProbe = new ReflectionProperty($cacheService, 'context');
    expect($cacheProbe->getValue($cacheService))->toBe($liveContext);
});
