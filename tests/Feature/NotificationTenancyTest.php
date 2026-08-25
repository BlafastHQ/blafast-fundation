<?php

declare(strict_types=1);

use Blafast\Foundation\Models\DatabaseNotification;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Services\OrganizationContext;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Notifications\Notification;

/**
 * Task 18 (H2): notifications route through the package's org-scoped model and
 * are tenant-isolated — before, the base Laravel model served every org's rows
 * regardless of X-Organization-Id.
 */
class TenancyProbeNotification extends Notification
{
    public function __construct(public string $label) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['label' => $this->label];
    }
}

function notifyUnder(?Organization $org, User $user, string $label): void
{
    $context = app(OrganizationContext::class);

    if ($org !== null) {
        $context->set($org, $user);
    }

    $user->notify(new TenancyProbeNotification($label));
    $context->clear();
}

it('persists a non-null organization_id for org-scoped notifications', function () {
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->addUser($user, 'User');

    notifyUnder($org, $user, 'scoped');

    $row = DatabaseNotification::withoutGlobalScopes()->firstOrFail();
    expect($row)->toBeInstanceOf(DatabaseNotification::class)
        ->and($row->organization_id)->toBe($org->id);
});

it('shows only the current org (plus global rows) — the H2 reproduction fails now', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $user = User::factory()->create();
    $orgA->addUser($user, 'User');
    $orgB->addUser($user, 'User');

    notifyUnder($orgA, $user, 'from-A');
    notifyUnder($orgB, $user, 'from-B');
    notifyUnder(null, $user, 'global'); // no context ⇒ null organization_id

    $labels = $this->actingAs($user, 'sanctum')
        ->getJson('/api/v1/notifications', ['X-Organization-Id' => $orgA->id])
        ->assertOk()
        ->json();

    $encoded = json_encode($labels);
    expect($encoded)->toContain('from-A')
        ->and($encoded)->not->toContain('from-B')
        // global/system rows (e.g. JobFailedNotification) stay visible
        ->and($encoded)->toContain('global');
});

it('does not filter in global context', function () {
    $orgA = Organization::factory()->create();
    $user = User::factory()->create();
    $orgA->addUser($user, 'User');

    notifyUnder($orgA, $user, 'from-A');
    notifyUnder(null, $user, 'global');

    app(OrganizationContext::class)->setGlobalContext($user);
    $count = DatabaseNotification::count();
    app(OrganizationContext::class)->clear();

    expect($count)->toBe(2);
});
