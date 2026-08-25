<?php

declare(strict_types=1);

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

it('executes an RPC with a custom-namespace user class (M19)', function () {
    Schema::create('test_sales_orders', function ($table) {
        $table->uuid('id')->primary();
        $table->string('reference');
        $table->boolean('approved')->default(false);
        $table->timestamps();
    });
    Relation::morphMap(['sales-order-model' => SalesOrderModel::class]);

    $user = User::factory()->create();
    $order = SalesOrderModel::create(['reference' => 'SO-9']);

    // The old queued mechanism (ExecuteModelMethod, deleted in task 27 when
    // ->queued() moved onto the deferred-request infrastructure) carried a
    // hard App\Models\User import that fataled for custom user namespaces.
    // The execution service is user-model-agnostic; the QUEUED path with this
    // suite's custom-namespace user is proven end-to-end in
    // RpcSlugAndQueueTest ('202 with a trackable id') and the replay-as-
    // original-user contract in DeferredExecutionTest.
    $method = SalesOrderModel::getApiMethod('approve');
    app(MethodExecutionService::class)->execute($order, $method, [], $user);

    expect($order->fresh()->approved)->toBeTrue();
});
