<?php

declare(strict_types=1);

use Blafast\Foundation\Enums\ApiErrorCode;
use Blafast\Foundation\Exceptions\JsonApiExceptionHandler;
use Blafast\Foundation\Models\Organization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test routes that throw various exceptions
    Route::get('/api/test/validation', function () {
        throw ValidationException::withMessages([
            'email' => ['The email field is required.'],
            'name' => ['The name field must be at least 3 characters.'],
        ]);
    });

    Route::get('/api/test/authentication', function () {
        throw new AuthenticationException('Unauthenticated.');
    });

    Route::get('/api/test/authorization', function () {
        throw new AuthorizationException('This action is unauthorized.');
    });

    Route::get('/api/test/model-not-found', function () {
        throw (new ModelNotFoundException)->setModel(Organization::class);
    });

    Route::get('/api/test/not-found', function () {
        throw new NotFoundHttpException('Resource not found.');
    });

    Route::get('/api/test/method-not-allowed', function () {
        throw new MethodNotAllowedHttpException(['GET', 'POST']);
    });

    Route::get('/api/test/http-exception', function () {
        throw new HttpException(429, 'Too many requests.');
    });

    Route::get('/api/test/generic', function () {
        throw new \Exception('Something went wrong.');
    });
});

test('validation exception returns correct JSON:API format', function () {
    $response = $this->get('/api/test/validation');

    $response->assertStatus(422);
    $response->assertJsonStructure([
        'errors' => [
            '*' => [
                'status',
                'code',
                'title',
                'detail',
                'source',
            ],
        ],
    ]);

    $errors = $response->json('errors');
    expect($errors)->toHaveCount(2);

    $emailError = collect($errors)->firstWhere('source.pointer', '/data/attributes/email');
    expect($emailError)->not->toBeNull()
        ->and($emailError['status'])->toBe('422')
        ->and($emailError['code'])->toBe(ApiErrorCode::VALIDATION_ERROR->value)
        ->and($emailError['title'])->toBe(ApiErrorCode::VALIDATION_ERROR->title())
        ->and($emailError['detail'])->toBe('The email field is required.');
});

test('authentication exception returns correct JSON:API format', function () {
    $response = $this->get('/api/test/authentication');

    $response->assertStatus(401);
    $response->assertJson([
        'errors' => [[
            'status' => '401',
            'code' => ApiErrorCode::AUTHENTICATION_REQUIRED->value,
            'title' => ApiErrorCode::AUTHENTICATION_REQUIRED->title(),
            'detail' => 'Unauthenticated.',
        ]],
    ]);
});

test('authorization exception returns correct JSON:API format', function () {
    $response = $this->get('/api/test/authorization');

    $response->assertStatus(403);
    $response->assertJson([
        'errors' => [[
            'status' => '403',
            'code' => ApiErrorCode::ACCESS_DENIED->value,
            'title' => ApiErrorCode::ACCESS_DENIED->title(),
            'detail' => 'This action is unauthorized.',
        ]],
    ]);
});

test('model not found exception returns correct JSON:API format', function () {
    $response = $this->get('/api/test/model-not-found');

    $response->assertStatus(404);
    $response->assertJson([
        'errors' => [[
            'status' => '404',
            'code' => ApiErrorCode::RESOURCE_NOT_FOUND->value,
            'title' => ApiErrorCode::RESOURCE_NOT_FOUND->title(),
        ]],
    ]);

    expect($response->json('errors.0.detail'))->toContain('Organization');
});

test('not found exception returns correct JSON:API format', function () {
    $response = $this->get('/api/test/not-found');

    $response->assertStatus(404);
    $response->assertJson([
        'errors' => [[
            'status' => '404',
            'code' => ApiErrorCode::RESOURCE_NOT_FOUND->value,
            'title' => ApiErrorCode::RESOURCE_NOT_FOUND->title(),
            'detail' => 'Resource not found.',
        ]],
    ]);
});

test('method not allowed exception returns correct JSON:API format', function () {
    $response = $this->get('/api/test/method-not-allowed');

    $response->assertStatus(405);
    $response->assertJson([
        'errors' => [[
            'status' => '405',
            'code' => ApiErrorCode::METHOD_NOT_ALLOWED->value,
            'title' => ApiErrorCode::METHOD_NOT_ALLOWED->title(),
        ]],
    ]);
});

test('HTTP exception returns correct JSON:API format', function () {
    $response = $this->get('/api/test/http-exception');

    $response->assertStatus(429);
    $response->assertJson([
        'errors' => [[
            'status' => '429',
            'code' => ApiErrorCode::RATE_LIMIT_EXCEEDED->value,
            'title' => ApiErrorCode::RATE_LIMIT_EXCEEDED->title(),
            'detail' => 'Too many requests.',
        ]],
    ]);
});

test('generic exception returns correct JSON:API format in production', function () {
    config(['app.debug' => false]);

    $response = $this->get('/api/test/generic');

    $response->assertStatus(500);
    $response->assertJson([
        'errors' => [[
            'status' => '500',
            'code' => ApiErrorCode::INTERNAL_ERROR->value,
            'title' => ApiErrorCode::INTERNAL_ERROR->title(),
            'detail' => 'An unexpected error occurred. Please try again later.',
        ]],
    ]);

    expect($response->json('errors.0'))->not->toHaveKey('meta');
});

test('generic exception includes debug info in development', function () {
    config(['app.debug' => true]);

    $response = $this->get('/api/test/generic');

    $response->assertStatus(500);
    $response->assertJsonStructure([
        'errors' => [
            '*' => [
                'status',
                'code',
                'title',
                'detail',
                'meta' => [
                    'exception',
                    'file',
                    'line',
                    'trace',
                ],
            ],
        ],
    ]);

    expect($response->json('errors.0.meta.exception'))->toBe(\Exception::class);
});

test('non-API request does not return JSON:API format', function () {
    Route::get('/non-api/test', function () {
        throw new \Exception('Non-API exception');
    });

    // The exception handler should return null for non-API requests
    // so Laravel's default exception handler takes over
    $handler = new JsonApiExceptionHandler;
    $request = Request::create('/non-api/test');
    $exception = new \Exception('Non-API exception');

    $result = $handler->render($request, $exception);

    expect($result)->toBeNull();
});

test('response macros work correctly for error responses', function () {
    Route::get('/api/test/macro-error', function () {
        return response()->jsonApiError(
            ApiErrorCode::VALIDATION_ERROR,
            'Test validation error',
            422,
            ['pointer' => '/data/attributes/field']
        );
    });

    $response = $this->get('/api/test/macro-error');

    $response->assertStatus(422);
    $response->assertJson([
        'errors' => [[
            'status' => '422',
            'code' => ApiErrorCode::VALIDATION_ERROR->value,
            'title' => ApiErrorCode::VALIDATION_ERROR->title(),
            'detail' => 'Test validation error',
            'source' => ['pointer' => '/data/attributes/field'],
        ]],
    ]);
});

test('response macros work correctly for success responses', function () {
    Route::get('/api/test/macro-success', function () {
        return response()->jsonApiSuccess(
            [
                'type' => 'test-resource',
                'id' => '123',
                'attributes' => ['name' => 'Test'],
            ],
            ['count' => 1]
        );
    });

    $response = $this->get('/api/test/macro-success');

    $response->assertStatus(200);
    $response->assertJson([
        'data' => [
            'type' => 'test-resource',
            'id' => '123',
            'attributes' => ['name' => 'Test'],
        ],
        'meta' => ['count' => 1],
    ]);
});

test('response macros work correctly for collection responses', function () {
    Route::get('/api/test/macro-collection', function () {
        return response()->jsonApiCollection(
            [
                ['type' => 'test-resource', 'id' => '1', 'attributes' => ['name' => 'Test 1']],
                ['type' => 'test-resource', 'id' => '2', 'attributes' => ['name' => 'Test 2']],
            ],
            ['total' => 2],
            ['self' => '/api/test']
        );
    });

    $response = $this->get('/api/test/macro-collection');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [
            '*' => ['type', 'id', 'attributes'],
        ],
        'meta',
        'links',
    ]);

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(2)
        ->and($response->json('links.self'))->toBe('/api/test');
});
