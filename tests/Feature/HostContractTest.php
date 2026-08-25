<?php

declare(strict_types=1);

use Blafast\Foundation\Jobs\ExecuteModelMethod;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Models\Role;
use Blafast\Foundation\Notifications\JobFailedNotification;
use Blafast\Foundation\Services\MethodExecutionService;
use Blafast\Foundation\Tests\Fixtures\RecordTeamJob;
use Blafast\Foundation\Tests\Fixtures\SalesOrderModel;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;

/**
 * Task 21 (H18/H19/M19): the package no longer assumes a fixed user class or an
 * enforced morph map. This suite's own user IS a custom-namespace class
 * (Blafast\Foundation\Tests\Fixtures\User) resolved purely from auth config.
 */
class UnmappedHostModel extends Model
{
    protected $table = 'unmapped_host_models';
}

it('maps the user alias from auth config and does not enforce the map on host models (H18)', function () {
    // The alias resolves to THIS suite's custom-namespace user, with no
    // environment branching and no fixture imports in src/.
    expect(Relation::getMorphedModel('user'))->toBe(User::class)
        ->and(Relation::getMorphedModel('organization'))->toBe(Organization::class);

    // A host model with NO alias uses its class name — the old enforced map
    // threw ClassMorphViolation here.
    expect((new UnmappedHostModel)->getMorphClass())->toBe(UnmappedHostModel::class);
});

it('notifies a real Superadmin when a BlaFastJob fails (H19)', function () {
    Notification::fake();

    $superadmin = User::factory()->create();
    Role::findOrCreate('Superadmin', 'api');
    $superadmin->assignRole('Superadmin');

    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->addUser($user, 'User');

    // Dispatch under an org context so the notification carries the org id
    // through the new public accessor (the old direct property read threw).
    organization_context()->set($org, $user);
    $job = new RecordTeamJob;
    organization_context()->clear();

    $job->failed(new RuntimeException('boom'));

    Notification::assertSentTo($superadmin, JobFailedNotification::class);
});

it('executes a queued RPC with a custom-namespace user class (M19)', function () {
    Schema::create('test_sales_orders', function ($table) {
        $table->uuid('id')->primary();
        $table->string('reference');
        $table->boolean('approved')->default(false);
        $table->timestamps();
    });
    Relation::morphMap(['sales-order-model' => SalesOrderModel::class]);

    $user = User::factory()->create();
    $order = SalesOrderModel::create(['reference' => 'SO-9']);

    // The old hard App\Models\User import fataled with "class not found" here.
    (new ExecuteModelMethod(SalesOrderModel::class, $order->id, 'approve', [], $user->id))
        ->handle(app(MethodExecutionService::class));

    expect($order->fresh()->approved)->toBeTrue();
});
