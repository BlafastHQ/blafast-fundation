<?php

declare(strict_types=1);

use Blafast\Foundation\Jobs\Middleware\RestoreOrganizationContext;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Tests\Fixtures\RecordTeamJob;
use Blafast\Foundation\Tests\Fixtures\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Task 17 (H9): job context restore fails CLOSED — a job whose organization is
 * gone fails outright instead of running with an empty (or, before task 12,
 * an all-tenant) view — and the reflection write is replaced by setForJob().
 */
it('fails the job outright when the organization no longer exists (H9)', function () {
    $org = Organization::factory()->create();
    $middleware = new RestoreOrganizationContext($org->id);
    $org->delete();

    $job = new class
    {
        public ?Throwable $failedWith = null;

        public bool $ran = false;

        public function fail(?Throwable $exception = null): void
        {
            $this->failedWith = $exception;
        }
    };

    $middleware->handle($job, function ($j) {
        $j->ran = true;
    });

    // The failure itself is asserted — not merely empty query results.
    expect($job->failedWith)->toBeInstanceOf(RuntimeException::class)
        ->and($job->failedWith->getMessage())->toContain($org->id)
        ->and($job->ran)->toBeFalse();
});

it('setForJob leaves a coherent, deliberate job context', function () {
    $org = Organization::factory()->create();
    $context = app(OrganizationContext::class);

    $context->setForJob($org);

    expect($context->hasContext())->toBeTrue()
        ->and($context->id())->toBe($org->id)
        ->and($context->user())->toBeNull() // deliberate: jobs have no user
        ->and($context->isGlobalContext())->toBeFalse()
        ->and(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($org->id);

    $context->clear();
    expect($context->hasContext())->toBeFalse()
        ->and(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});

it('never shares context or team id between two consecutive jobs', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $observed = [];
    $job = new stdClass;

    (new RestoreOrganizationContext($orgA->id))->handle($job, function () use (&$observed) {
        $observed['a'] = [organization_context()->id(), app(PermissionRegistrar::class)->getPermissionsTeamId()];
    });
    $observed['between'] = [organization_context()->id(), app(PermissionRegistrar::class)->getPermissionsTeamId()];

    (new RestoreOrganizationContext($orgB->id))->handle($job, function () use (&$observed) {
        $observed['b'] = [organization_context()->id(), app(PermissionRegistrar::class)->getPermissionsTeamId()];
    });

    expect($observed['a'])->toBe([$orgA->id, $orgA->id])
        ->and($observed['between'])->toBe([null, null])
        ->and($observed['b'])->toBe([$orgB->id, $orgB->id]);
});

it('restores the context under a REAL queue worker, not only sync', function () {
    config()->set('queue.default', 'database');

    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->addUser($user, 'User');

    // Dispatch WITH an org context captured (BlaFastJob snapshots it).
    app(OrganizationContext::class)->set($org, $user);
    RecordTeamJob::dispatch();
    app(OrganizationContext::class)->clear();

    expect(DB::table('jobs')->count())->toBe(1);

    $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertSuccessful();

    $seen = cache()->get('record-team-job');
    expect($seen['context_org_id'])->toBe($org->id)
        ->and($seen['team_id'])->toBe($org->id)
        ->and($seen['has_context'])->toBeTrue()
        // and the worker process context is clean afterwards
        ->and(organization_context()->hasContext())->toBeFalse()
        ->and(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});
