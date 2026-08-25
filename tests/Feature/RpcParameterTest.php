<?php

declare(strict_types=1);

use Blafast\Foundation\Services\ModelRegistry;
use Blafast\Foundation\Tests\Fixtures\SalesOrderModel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Task 26 (H16/H17): RPC parameter validation, casting and binding.
 *
 * H16 — `array:<type>` used to append the raw modifier ('email.*') to the
 * PARENT attribute's rules; Laravel studly-cased it into a nonexistent
 * validateEmail.* method, so every modified-array input 500'd instead of 422.
 * H17 — raw request values were splatted positionally under strict_types:
 * GET '5' TypeError'd into int parameters, omitted optionals materialised as
 * null and overrode PHP defaults, and declaration-order drift misbound.
 */
beforeEach(function () {
    Schema::create('test_sales_orders', function ($table) {
        $table->uuid('id')->primary();
        $table->string('reference');
        $table->boolean('approved')->default(false);
        $table->timestamps();
    });

    app(ModelRegistry::class)->register(SalesOrderModel::class);

    actingAsSuperadmin();

    $this->order = SalesOrderModel::create(['reference' => 'SO-1']);
});

function callRpc(string $method, array $attributes = [], string $verb = 'post', string $query = '')
{
    $url = '/api/v1/sales-order-model/'.test()->order->id."/call/{$method}{$query}";

    return $verb === 'get'
        ? test()->getJson($url)
        : test()->postJson($url, ['data' => ['attributes' => $attributes]]);
}

it('returns 422 (not 500) for an invalid array:email element', function () {
    callRpc('notify', ['emails' => ['not-an-email']])
        ->assertStatus(422)
        ->assertJsonPath('errors.0.status', '422');
});

it('returns 422 (not 500) for an invalid array:datetime element', function () {
    callRpc('notify', ['emails' => ['a@b.example'], 'at' => ['not-a-date']])
        ->assertStatus(422);
});

it('returns 422 (not 500) for an invalid array:float element', function () {
    callRpc('notify', ['emails' => ['a@b.example'], 'weights' => ['heavy']])
        ->assertStatus(422);
});

it('accepts valid modified arrays and casts their elements', function () {
    callRpc('notify', [
        'emails' => ['a@b.example', 'c@d.example'],
        'at' => ['2026-08-25 10:00:00'],
        'weights' => ['1.5', '2'],
    ])
        ->assertOk()
        ->assertJsonPath('data.attributes.result.emails', ['a@b.example', 'c@d.example'])
        ->assertJsonPath('data.attributes.result.weight_types', ['float', 'float']);
});

it('binds a GET ?copies=5 as int 5 without a TypeError', function () {
    callRpc('print', verb: 'get', query: '?copies=5')
        ->assertOk()
        ->assertJsonPath('data.attributes.result.copies', 5)
        ->assertJsonPath('data.attributes.result.copies_type', 'int');
});

it('falls through to the PHP default when an optional is omitted', function () {
    // The old code materialised the missing optional as null and forced it
    // over `int $copies = 1` — a guaranteed TypeError.
    callRpc('print', verb: 'get')
        ->assertOk()
        ->assertJsonPath('data.attributes.result.copies', 1);
});

it('binds by name when the declared order differs from the PHP signature', function () {
    // Declared (carrier, boxes) vs ship(int $boxes, string $carrier): the old
    // positional splat called ship('ups', 3).
    callRpc('ship', ['boxes' => 3])
        ->assertOk()
        ->assertJsonPath('data.attributes.result.boxes', 3)
        ->assertJsonPath('data.attributes.result.boxes_type', 'int')
        ->assertJsonPath('data.attributes.result.carrier', 'ups');

    callRpc('ship', ['boxes' => '7', 'carrier' => 'dhl'])
        ->assertOk()
        ->assertJsonPath('data.attributes.result.boxes', 7)
        ->assertJsonPath('data.attributes.result.carrier', 'dhl');
});

it('executes a queued method with the same inputs in the worker', function () {
    config()->set('queue.default', 'sync');

    // '2' (string) exercises the cast-before-queue path end-to-end: the job
    // must receive int 2 and bind it into `int $copies = 1`.
    callRpc('approve-later', ['copies' => '2'])
        ->assertOk()
        ->assertJsonPath('data.attributes.result.queued', true);

    expect($this->order->fresh()->reference)->toBe('approved-x2');
});

it('passes every ApiMethodParameterType end-to-end with correct PHP types', function () {
    $response = test()->post(
        '/api/v1/sales-order-model/'.$this->order->id.'/call/echo-types',
        [
            'data' => [
                'attributes' => [
                    'note' => 'hello',
                    'count' => '42',
                    'ratio' => '0.5',
                    'urgent' => '1',
                    'contact' => 'ops@blafast.io',
                    'ref' => (string) Str::uuid(),
                    'day' => '2026-08-25',
                    'at' => '2026-08-25 10:00:00',
                    'tags' => ['a', 'b'],
                    'blob' => '{"nested":true}',
                    'size' => 'a4',
                    'doc' => UploadedFile::fake()->create('spec.pdf', 10, 'application/pdf'),
                ],
            ],
        ],
        ['Accept' => 'application/json'],
    );

    $response->assertOk();

    $types = $response->json('data.attributes.result.types');
    $doc = $types['doc'];
    unset($types['doc']);

    expect($types)->toBe([
        'note' => 'string',
        'count' => 'int',
        'ratio' => 'float',
        'urgent' => 'bool',
        'contact' => 'string',
        'ref' => 'string',
        'day' => 'string',
        'at' => 'string',
        'tags' => 'array',
        'blob' => 'string',
        'size' => 'string',
    ])
        // The test client hands over Testing\File — an UploadedFile subclass.
        ->and(is_a($doc, UploadedFile::class, true))->toBeTrue()
        ->and($response->json('data.attributes.result.count'))->toBe(42)
        ->and($response->json('data.attributes.result.urgent'))->toBeTrue();
});

it('rejects an out-of-range enum with 422', function () {
    callRpc('notify', ['emails' => 'not-even-an-array'])->assertStatus(422);

    test()->postJson('/api/v1/sales-order-model/'.$this->order->id.'/call/ship', [
        'data' => ['attributes' => ['boxes' => 'many']],
    ])->assertStatus(422);
});
