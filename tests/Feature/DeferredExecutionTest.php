<?php

declare(strict_types=1);

use Blafast\Foundation\Enums\DeferredRequestStatus;
use Blafast\Foundation\Jobs\ProcessDeferredApiRequest;
use Blafast\Foundation\Models\DeferredApiRequest;
use Blafast\Foundation\Models\DeferredEndpointConfig;
use Blafast\Foundation\Models\Organization;
use Blafast\Foundation\Tests\Fixtures\User;
use Illuminate\Support\Facades\Queue;

/**
 * Task 15 (C2/H10): deferred requests execute IN PROCESS as the original user
 * (the old HTTP replay dropped Authorization and stored the 401 as "completed"),
 * and outcomes are classified honestly: 2xx completed, 4xx terminal failure,
 * 5xx retried per max_attempts.
 */
function deferThrough(string $endpoint, array $payload = []): array
{
    $org = Organization::factory()->create();
    $user = User::factory()->create();
    $org->addUser($user, 'User');

    DeferredEndpointConfig::withoutOrganizationScope()->create([
        'organization_id' => null,
        'http_method' => 'POST',
        'endpoint_pattern' => 'api/v1/test-deferred/*',
        'is_active' => true,
        'force_deferred' => false,
        'priority' => 'default',
    ]);

    Queue::fake();

    $response = test()->actingAs($user, 'sanctum')
        ->postJson($endpoint, ['data' => $payload], [
            'X-Organization-Id' => $org->id,
            'X-Blafast-Defer' => 'true',
        ]);

    $response->assertStatus(202);

    $deferred = DeferredApiRequest::withoutOrganizationScope()->firstOrFail();

    return [$deferred, $user, $org];
}

it('executes a deferred call to an auth:sanctum route as the original user and org (C2)', function () {
    [$deferred, $user, $org] = deferThrough('/api/v1/test-deferred/echo', ['answer' => 42]);

    (new ProcessDeferredApiRequest($deferred))->handle();

    $deferred->refresh();
    expect($deferred->status)->toBe(DeferredRequestStatus::Completed)
        ->and($deferred->result_status_code)->toBe(200)
        ->and($deferred->result['user_id'])->toBe($user->id)
        ->and($deferred->result['org_id'])->toBe($org->id)
        ->and($deferred->result['payload'])->toBe(['answer' => 42]);

    // Nothing leaks into the worker after the job.
    expect(auth()->guard('sanctum')->user())->toBeNull()
        ->and(organization_context()->hasContext())->toBeFalse();
});

it('records a downstream 422 as a terminal failure, never "completed" (H10)', function () {
    [$deferred] = deferThrough('/api/v1/test-deferred/unprocessable');

    (new ProcessDeferredApiRequest($deferred))->handle();

    $deferred->refresh();
    expect($deferred->status)->toBe(DeferredRequestStatus::Failed)
        ->and($deferred->error_code)->toBe('HTTP_422')
        ->and($deferred->result_status_code)->toBe(422)
        ->and($deferred->result)->toBe(['error' => 'nope']);
});

it('retries a transient 500 per max_attempts and only then fails (H10)', function () {
    cache()->put('test-deferred-flaky-hits', 0);

    [$deferred] = deferThrough('/api/v1/test-deferred/flaky');
    expect($deferred->max_attempts)->toBeGreaterThan(1);

    $job = new ProcessDeferredApiRequest($deferred);

    // Attempt 1: 500 → the job THROWS so the queue can retry (the old blanket
    // catch permanently failed on attempt 1 and made failed() unreachable).
    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
    expect($deferred->fresh()->status)->toBe(DeferredRequestStatus::Processing);

    // Attempt 2 (the route has warmed up): success.
    $job->handle();
    expect($deferred->fresh()->status)->toBe(DeferredRequestStatus::Completed);
});

it('marks failed with the real last status after retries exhaust', function () {
    // A route that always 500s: reuse flaky but keep it cold by resetting the
    // counter before every attempt.
    [$deferred] = deferThrough('/api/v1/test-deferred/flaky');
    $job = new ProcessDeferredApiRequest($deferred);

    $attempts = 0;
    while (true) {
        cache()->put('test-deferred-flaky-hits', 0); // stays cold: every hit 500s
        $attempts++;

        try {
            $job->handle();
            break;
        } catch (RuntimeException $e) {
            if ($attempts >= $deferred->max_attempts) {
                $job->failed($e);
                break;
            }
        }
    }

    $deferred->refresh();
    expect($deferred->status)->toBe(DeferredRequestStatus::Failed)
        ->and($deferred->error_code)->toBe('HTTP_500')
        ->and($deferred->result_status_code)->toBe(500);
});

it('reflects true outcomes end-to-end on the poll endpoint', function () {
    [$deferred, $user] = deferThrough('/api/v1/test-deferred/echo', ['k' => 'v']);

    // Queued → visible on the poll endpoint.
    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/deferred/{$deferred->id}", ['X-Organization-Id' => $deferred->organization_id])
        ->assertOk()
        ->assertJsonPath('data.attributes.status', 'pending');

    (new ProcessDeferredApiRequest($deferred))->handle();

    $this->actingAs($user, 'sanctum')
        ->getJson("/api/v1/deferred/{$deferred->id}", ['X-Organization-Id' => $deferred->organization_id])
        ->assertOk()
        ->assertJsonPath('data.attributes.status', 'completed');
});
