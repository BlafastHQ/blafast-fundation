<?php

declare(strict_types=1);

namespace Blafast\Foundation\Services;

use Blafast\Foundation\Dto\ApiMethod;
use Blafast\Foundation\Jobs\ExecuteModelMethod;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Service for executing model methods via API.
 *
 * Handles synchronous and asynchronous method execution,
 * activity logging, and parameter sanitization.
 */
class MethodExecutionService
{
    /**
     * Execute a model method.
     */
    public function execute(
        Model $model,
        ApiMethod $method,
        array $parameters,
        ?Authenticatable $user
    ): mixed {
        // Handle queued execution
        if ($method->queued) {
            return $this->queueExecution($model, $method, $parameters, $user);
        }

        // Execute synchronously
        $result = $this->executeMethod($model, $method, $parameters);

        // Log the execution
        $this->logExecution($model, $method, $parameters, $result, $user);

        return $result;
    }

    /**
     * Execute method directly on model.
     */
    public function executeMethod(Model $model, ApiMethod $method, array $parameters): mixed
    {
        $methodName = $method->method;

        // Verify method exists on model
        if (! method_exists($model, $methodName)) {
            throw new \RuntimeException(
                "Method '{$methodName}' does not exist on model."
            );
        }

        // Call the method with parameters
        return $model->{$methodName}(...array_values($parameters));
    }

    /**
     * Queue method execution for background processing.
     *
     * @return array<string, mixed>
     */
    protected function queueExecution(
        Model $model,
        ApiMethod $method,
        array $parameters,
        ?Authenticatable $user
    ): array {
        // Dispatch job
        $job = new ExecuteModelMethod(
            get_class($model),
            // @phpstan-ignore property.notFound
            $model->id,
            $method->slug,
            $parameters,
            // @phpstan-ignore property.notFound
            $user?->id
        );

        dispatch($job);

        return [
            'queued' => true,
            'message' => 'Method execution has been queued.',
        ];
    }

    /**
     * Log method execution to activity log.
     */
    public function logExecution(
        Model $model,
        ApiMethod $method,
        array $parameters,
        mixed $result,
        ?Authenticatable $user
    ): void {
        activity()
            ->performedOn($model)
            // @phpstan-ignore argument.type
            ->causedBy($user)
            ->withProperties([
                'method' => $method->slug,
                'parameters' => $this->sanitizeParameters($parameters, $method),
                'result_summary' => $this->summarizeResult($result),
            ])
            ->log('method_executed');
    }

    /**
     * Sanitize parameters for logging (redact sensitive values).
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function sanitizeParameters(array $parameters, ApiMethod $method): array
    {
        $sanitized = [];

        foreach ($parameters as $key => $value) {
            $param = $method->parameters[$key] ?? null;

            // Redact sensitive parameters
            if ($param && $this->isSensitive($param->name, $param->type)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if parameter is sensitive and should be redacted.
     */
    protected function isSensitive(string $name, string $type): bool
    {
        $sensitiveNames = ['password', 'secret', 'token', 'key', 'credential'];

        foreach ($sensitiveNames as $sensitive) {
            if (str_contains(strtolower($name), $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Summarize result for logging (truncate long strings).
     */
    protected function summarizeResult(mixed $result): mixed
    {
        if (is_array($result)) {
            return array_map(function ($value) {
                if (is_string($value) && strlen($value) > 100) {
                    return substr($value, 0, 100).'...';
                }

                return $value;
            }, $result);
        }

        if (is_string($result) && strlen($result) > 100) {
            return substr($result, 0, 100).'...';
        }

        return $result;
    }
}
