<?php

declare(strict_types=1);

namespace Blafast\Foundation\Exceptions;

use Blafast\Foundation\Enums\ApiErrorCode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * JSON:API Exception Handler
 *
 * Transforms Laravel exceptions into JSON:API compliant error responses.
 */
class JsonApiExceptionHandler
{
    /**
     * Render an exception as a JSON:API error response.
     */
    public function render(Request $request, Throwable $e): ?JsonResponse
    {
        // Only handle API requests
        if (! $this->wantsJsonApi($request)) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => $this->renderValidation($e),
            $e instanceof AuthenticationException => $this->renderUnauthenticated($e),
            $e instanceof AuthorizationException => $this->renderAuthorization($e),
            $e instanceof ModelNotFoundException => $this->renderModelNotFound($e),
            $e instanceof NotFoundHttpException => $this->renderNotFound($e),
            $e instanceof MethodNotAllowedHttpException => $this->renderMethodNotAllowed($e),
            $e instanceof HttpException => $this->renderHttp($e),
            default => $this->renderGeneric($e),
        };
    }

    /**
     * Determine if the request expects a JSON:API response.
     */
    private function wantsJsonApi(Request $request): bool
    {
        // Check if the request path starts with /api
        if ($request->is('api/*')) {
            return true;
        }

        // Check if the request expects JSON
        if ($request->expectsJson()) {
            return true;
        }

        // Check for JSON:API Accept header
        if ($request->header('Accept') === 'application/vnd.api+json') {
            return true;
        }

        return false;
    }

    /**
     * Render a validation exception.
     */
    private function renderValidation(ValidationException $e): JsonResponse
    {
        $errors = [];

        foreach ($e->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $errors[] = [
                    'status' => '422',
                    'code' => ApiErrorCode::VALIDATION_ERROR->value,
                    'title' => ApiErrorCode::VALIDATION_ERROR->title(),
                    'detail' => $message,
                    'source' => [
                        'pointer' => "/data/attributes/{$field}",
                    ],
                ];
            }
        }

        return response()->json(['errors' => $errors], 422);
    }

    /**
     * Render an authentication exception.
     */
    private function renderUnauthenticated(AuthenticationException $e): JsonResponse
    {
        $error = [
            'status' => '401',
            'code' => ApiErrorCode::AUTHENTICATION_REQUIRED->value,
            'title' => ApiErrorCode::AUTHENTICATION_REQUIRED->title(),
            'detail' => $e->getMessage() ?: 'You must be authenticated to access this resource.',
        ];

        return response()->json(['errors' => [$error]], 401);
    }

    /**
     * Render an authorization exception.
     */
    private function renderAuthorization(AuthorizationException $e): JsonResponse
    {
        $error = [
            'status' => '403',
            'code' => ApiErrorCode::ACCESS_DENIED->value,
            'title' => ApiErrorCode::ACCESS_DENIED->title(),
            'detail' => $e->getMessage() ?: 'You do not have permission to perform this action.',
        ];

        return response()->json(['errors' => [$error]], 403);
    }

    /**
     * Render a model not found exception.
     */
    private function renderModelNotFound(ModelNotFoundException $e): JsonResponse
    {
        $modelName = class_basename($e->getModel() ?: 'Resource');

        $error = [
            'status' => '404',
            'code' => ApiErrorCode::RESOURCE_NOT_FOUND->value,
            'title' => ApiErrorCode::RESOURCE_NOT_FOUND->title(),
            'detail' => "{$modelName} not found.",
        ];

        return response()->json(['errors' => [$error]], 404);
    }

    /**
     * Render a not found HTTP exception.
     */
    private function renderNotFound(NotFoundHttpException $e): JsonResponse
    {
        $error = [
            'status' => '404',
            'code' => ApiErrorCode::RESOURCE_NOT_FOUND->value,
            'title' => ApiErrorCode::RESOURCE_NOT_FOUND->title(),
            'detail' => $e->getMessage() ?: 'The requested resource was not found.',
        ];

        return response()->json(['errors' => [$error]], 404);
    }

    /**
     * Render a method not allowed exception.
     */
    private function renderMethodNotAllowed(MethodNotAllowedHttpException $e): JsonResponse
    {
        $error = [
            'status' => '405',
            'code' => ApiErrorCode::METHOD_NOT_ALLOWED->value,
            'title' => ApiErrorCode::METHOD_NOT_ALLOWED->title(),
            'detail' => $e->getMessage() ?: 'The HTTP method is not allowed for this endpoint.',
        ];

        return response()->json(['errors' => [$error]], 405);
    }

    /**
     * Render an HTTP exception.
     */
    private function renderHttp(HttpException $e): JsonResponse
    {
        $statusCode = $e->getStatusCode();
        $code = $this->getCodeForStatus($statusCode);

        $error = [
            'status' => (string) $statusCode,
            'code' => $code->value,
            'title' => $code->title(),
            'detail' => $e->getMessage() ?: 'An error occurred while processing your request.',
        ];

        return response()->json(['errors' => [$error]], $statusCode);
    }

    /**
     * Render a generic exception.
     */
    private function renderGeneric(Throwable $e): JsonResponse
    {
        $debug = config('app.debug', false);

        $error = [
            'status' => '500',
            'code' => ApiErrorCode::INTERNAL_ERROR->value,
            'title' => ApiErrorCode::INTERNAL_ERROR->title(),
            'detail' => $debug ? $e->getMessage() : 'An unexpected error occurred. Please try again later.',
        ];

        if ($debug) {
            $error['meta'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(5)->toArray(),
            ];
        }

        return response()->json(['errors' => [$error]], 500);
    }

    /**
     * Get the appropriate error code for an HTTP status code.
     */
    private function getCodeForStatus(int $status): ApiErrorCode
    {
        return match ($status) {
            400 => ApiErrorCode::BAD_REQUEST,
            401 => ApiErrorCode::AUTHENTICATION_REQUIRED,
            403 => ApiErrorCode::ACCESS_DENIED,
            404 => ApiErrorCode::RESOURCE_NOT_FOUND,
            405 => ApiErrorCode::METHOD_NOT_ALLOWED,
            409 => ApiErrorCode::CONFLICT,
            422 => ApiErrorCode::VALIDATION_ERROR,
            429 => ApiErrorCode::RATE_LIMIT_EXCEEDED,
            503 => ApiErrorCode::SERVICE_UNAVAILABLE,
            default => ApiErrorCode::INTERNAL_ERROR,
        };
    }
}
