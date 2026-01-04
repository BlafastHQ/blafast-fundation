<?php

declare(strict_types=1);

use Blafast\Foundation\Enums\DeferredRequestStatus;
use Blafast\Foundation\Models\DeferredApiRequest;
use Blafast\Foundation\Models\DeferredEndpointConfig;
use Blafast\Foundation\Models\Organization;
use Illuminate\Support\Facades\Queue;

describe('Deferred API Requests', function () {
    it('can list user deferred requests', function () {
        $org = Organization::factory()->create();
        actingAsOrgAdmin($org);

        // Create some deferred requests for this user
        DeferredApiRequest::factory()->count(3)->create([
            'user_id' => auth()->id(),
            'organization_id' => $org->id,
        ]);

        // Create a request for another user (should not be visible)
        DeferredApiRequest::factory()->create([
            'organization_id' => $org->id,
        ]);

        $response = $this->getJson('/api/v1/deferred');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter deferred requests by status', function () {
        $org = Organization::factory()->create();
        actingAsOrgAdmin($org);

        DeferredApiRequest::factory()->pending()->create([
            'user_id' => auth()->id(),
            'organization_id' => $org->id,
        ]);

        DeferredApiRequest::factory()->completed()->create([
            'user_id' => auth()->id(),
            'organization_id' => $org->id,
        ]);

        $response = $this->getJson('/api/v1/deferred?filter[status]=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.attributes.status', 'pending');
    });

    it('can get status of a specific deferred request', function () {
        $org = Organization::factory()->create();
        actingAsOrgAdmin($org);

        $deferred = DeferredApiRequest::factory()->processing()->create([
            'user_id' => auth()->id(),
            'organization_id' => $org->id,
        ]);

        $response = $this->getJson("/api/v1/deferred/{$deferred->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $deferred->id)
            ->assertJsonPath('data.attributes.status', 'processing')
            ->assertJsonPath('data.attributes.progress', 50);
    });

    it('cannot access another user deferred request', function () {
        $org = Organization::factory()->create();
        actingAsOrgAdmin($org);

        $deferred = DeferredApiRequest::factory()->create([
            'organization_id' => $org->id,
        ]);

        $response = $this->getJson("/api/v1/deferred/{$deferred->id}");

        $response->assertNotFound();
    });

    it('can cancel a pending deferred request', function () {
        $org = Organization::factory()->create();
        actingAsOrgAdmin($org);

        $deferred = DeferredApiRequest::factory()->pending()->create([
            'user_id' => auth()->id(),
            'organization_id' => $org->id,
        ]);

        $response = $this->postJson("/api/v1/deferred/{$deferred->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.attributes.status', 'cancelled');

        expect($deferred->fresh()->status)->toBe(DeferredRequestStatus::Cancelled);
    });

    it('cannot cancel a non-pending request', function () {
        $org = Organization::factory()->create();
        actingAsOrgAdmin($org);

        $deferred = DeferredApiRequest::factory()->completed()->create([
            'user_id' => auth()->id(),
            'organization_id' => $org->id,
        ]);

        $response = $this->postJson("/api/v1/deferred/{$deferred->id}/cancel");

        $response->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'CANNOT_CANCEL');
    });

    it('can retry a failed request', function () {
        Queue::fake();

        $org = Organization::factory()->create();
        actingAsOrgAdmin($org);

        $deferred = DeferredApiRequest::factory()->failed()->create([
            'user_id' => auth()->id(),
            'organization_id' => $org->id,
            'attempts' => 1,
            'max_attempts' => 3,
        ]);

        $response = $this->postJson("/api/v1/deferred/{$deferred->id}/retry");

        $response->assertOk()
            ->assertJsonPath('data.attributes.status', 'pending');

        expect($deferred->fresh()->status)->toBe(DeferredRequestStatus::Pending);
    });

    it('cannot retry a request that exceeded max attempts', function () {
        $org = Organization::factory()->create();
        actingAsOrgAdmin($org);

        $deferred = DeferredApiRequest::factory()->failed()->create([
            'user_id' => auth()->id(),
            'organization_id' => $org->id,
            'attempts' => 3,
            'max_attempts' => 3,
        ]);

        $response = $this->postJson("/api/v1/deferred/{$deferred->id}/retry");

        $response->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'CANNOT_RETRY');
    });

    it('returns completed request result', function () {
        $org = Organization::factory()->create();
        actingAsOrgAdmin($org);

        $result = ['data' => ['success' => true, 'value' => 42]];
        $deferred = DeferredApiRequest::factory()->completed($result)->create([
            'user_id' => auth()->id(),
            'organization_id' => $org->id,
        ]);

        $response = $this->getJson("/api/v1/deferred/{$deferred->id}");

        $response->assertOk()
            ->assertJsonPath('data.attributes.result', $result);
    });
});

describe('Deferred Request Middleware', function () {
    it('defers request with X-Blafast-Defer header when config exists', function () {
        Queue::fake();

        $org = Organization::factory()->create();
        actingAsOrgAdmin($org);

        // Create endpoint config
        DeferredEndpointConfig::factory()->create([
            'http_method' => 'GET',
            'endpoint_pattern' => 'api/v1/test/*',
            'force_deferred' => false,
            'is_active' => true,
        ]);

        $response = $this->withHeader('X-Blafast-Defer', 'true')
            ->getJson('/api/v1/test/endpoint');

        $response->assertStatus(202)
            ->assertJsonStructure([
                'data' => [
                    'type',
                    'id',
                    'attributes' => ['status', 'endpoint'],
                    'links' => ['self', 'poll'],
                ],
            ]);
    });

    it('does not defer request without X-Blafast-Defer header', function () {
        $org = Organization::factory()->create();
        actingAsOrgAdmin($org);

        DeferredEndpointConfig::factory()->create([
            'http_method' => 'GET',
            'endpoint_pattern' => 'api/v1/test/*',
            'force_deferred' => false,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/test/endpoint');

        // Should pass through normally (not deferred)
        $response->assertStatus(404); // Route doesn't actually exist
    });

    it('always defers request when force_deferred is true', function () {
        Queue::fake();

        $org = Organization::factory()->create();
        actingAsOrgAdmin($org);

        DeferredEndpointConfig::factory()->forceDeferred()->create([
            'http_method' => 'GET',
            'endpoint_pattern' => 'api/v1/test/*',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/test/endpoint');

        $response->assertStatus(202);
    });
});
