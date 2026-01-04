<?php

declare(strict_types=1);

namespace Blafast\Foundation\Http\Controllers\Api\V1;

use Blafast\Foundation\Contracts\HasApiMethods;
use Blafast\Foundation\Dto\ApiMethod;
use Blafast\Foundation\Services\ExecPermissionChecker;
use Blafast\Foundation\Services\MethodExecutionService;
use Blafast\Foundation\Services\ModelRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Controller for executing model methods via API.
 *
 * Handles all method execution requests including model resolution,
 * permission checking, parameter validation, and method execution.
 */
class ModelMethodController extends Controller
{
    public function __construct(
        private ModelRegistry $registry,
        private MethodExecutionService $executionService,
        private ExecPermissionChecker $permissionChecker,
    ) {}

    /**
     * Execute a model method.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function __invoke(
        Request $request,
        string $modelSlug,
        string $uuid,
        string $methodSlug
    ): JsonResponse {
        // 1. Resolve model class
        $modelClass = $this->resolveModelClass($modelSlug);

        // 2. Verify model supports API methods
        $this->ensureModelHasApiMethods($modelClass);

        // 3. Find model instance
        $model = $this->findModel($modelClass, $uuid);

        // 4. Get method definition
        $method = $this->getMethodDefinition($modelClass, $methodSlug);

        // 5. Validate HTTP method matches
        $this->validateHttpMethod($request, $method);

        // 6. Check permission
        $this->checkPermission($request->user(), $modelSlug, $methodSlug);

        // 7. Extract and validate parameters
        $parameters = $this->extractAndValidateParameters($request, $method);

        // 8. Execute method
        $result = $this->executionService->execute(
            $model,
            $method,
            $parameters,
            $request->user()
        );

        // 9. Return response
        return $this->formatResponse($model, $method, $result);
    }

    /**
     * Resolve model class from slug.
     *
     * @return class-string
     */
    protected function resolveModelClass(string $slug): string
    {
        $modelClass = $this->registry->get($slug);

        if (! $modelClass) {
            abort(404, "Model '{$slug}' not found.");
        }

        return $modelClass;
    }

    /**
     * Ensure model implements HasApiMethods.
     *
     * @param  class-string  $modelClass
     */
    protected function ensureModelHasApiMethods(string $modelClass): void
    {
        if (! in_array(HasApiMethods::class, class_implements($modelClass) ?: [])) {
            abort(404, 'This model does not expose API methods.');
        }
    }

    /**
     * Find model instance by UUID.
     *
     * @param  class-string  $modelClass
     */
    protected function findModel(string $modelClass, string $uuid): Model
    {
        $model = $modelClass::find($uuid);

        if (! $model) {
            abort(404, 'Resource not found.');
        }

        return $model;
    }

    /**
     * Get method definition from model.
     *
     * @param  class-string  $modelClass
     */
    protected function getMethodDefinition(string $modelClass, string $methodSlug): ApiMethod
    {
        $method = $modelClass::getApiMethod($methodSlug);

        if (! $method) {
            abort(404, "Method '{$methodSlug}' not found on this model.");
        }

        return $method;
    }

    /**
     * Validate HTTP method matches method definition.
     */
    protected function validateHttpMethod(Request $request, ApiMethod $method): void
    {
        if ($request->method() !== $method->httpMethod) {
            abort(405, "Method must be called with {$method->httpMethod}.");
        }
    }

    /**
     * Check if user has permission to execute method.
     */
    protected function checkPermission($user, string $modelSlug, string $methodSlug): void
    {
        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        if (! $this->permissionChecker->canExecute($user, $modelSlug, $methodSlug)) {
            abort(403, "You do not have permission to execute '{$methodSlug}' on this resource.");
        }
    }

    /**
     * Extract and validate method parameters.
     *
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function extractAndValidateParameters(Request $request, ApiMethod $method): array
    {
        // Extract parameters from request
        $data = $request->isMethod('GET')
            ? $request->query()
            : $request->input('data.attributes', []);

        // Build validation rules
        $rules = [];
        foreach ($method->parameters as $param) {
            $key = $request->isMethod('GET') ? $param->name : "data.attributes.{$param->name}";
            $rules[$key] = $param->validationRules();
        }

        // Validate
        $validator = Validator::make(
            $request->isMethod('GET') ? $request->query() : $request->all(),
            $rules
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Return validated parameters with defaults applied
        $validated = [];
        foreach ($method->parameters as $param) {
            $value = $data[$param->name] ?? $param->default;
            $validated[$param->name] = $value;
        }

        return $validated;
    }

    /**
     * Format JSON:API response.
     */
    protected function formatResponse(Model $model, ApiMethod $method, mixed $result): JsonResponse
    {
        // Handle file responses
        if ($method->returns && ($method->returns['type'] ?? '') === 'file') {
            return $this->formatFileResponse($result, $method);
        }

        return response()->json([
            'data' => [
                'type' => 'method-result',
                // @phpstan-ignore property.notFound
                'id' => $model->id,
                'attributes' => [
                    'method' => $method->slug,
                    // @phpstan-ignore staticMethod.notFound
                    'model' => $model::getApiSlug(),
                    'executed_at' => now()->toIso8601String(),
                    'result' => $result,
                ],
            ],
        ]);
    }

    /**
     * Format file response.
     */
    protected function formatFileResponse(mixed $result, ApiMethod $method): JsonResponse
    {
        // If result is a file path or URL
        if (is_string($result)) {
            return response()->json([
                'data' => [
                    'type' => 'file-result',
                    'attributes' => [
                        'url' => $result,
                        'mime' => $method->returns['mime'] ?? 'application/octet-stream',
                    ],
                ],
            ]);
        }

        // If result is file content or other format
        return response()->json([
            'data' => [
                'type' => 'file-result',
                'attributes' => $result,
            ],
        ]);
    }
}
