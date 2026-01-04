<?php

declare(strict_types=1);

namespace Blafast\Foundation\Jobs;

use Blafast\Foundation\Services\MethodExecutionService;

/**
 * Job for executing model methods in the background.
 *
 * Queued method executions are dispatched through this job
 * to handle long-running operations asynchronously.
 */
class ExecuteModelMethod extends BlaFastJob
{
    /**
     * Create a new job instance.
     *
     * @param  class-string  $modelClass
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        private string $modelClass,
        private string $modelId,
        private string $methodSlug,
        private array $parameters,
        private ?string $userId,
    ) {
        parent::__construct();
    }

    /**
     * Execute the job.
     */
    public function handle(MethodExecutionService $service): void
    {
        // Find the model instance
        $model = $this->modelClass::findOrFail($this->modelId);

        // Get the method definition
        $method = $this->modelClass::getApiMethod($this->methodSlug);

        if (! $method) {
            throw new \RuntimeException(
                "Method '{$this->methodSlug}' not found on model."
            );
        }

        // Find the user if provided
        $user = $this->userId ? \App\Models\User::find($this->userId) : null;

        // Execute the method (without re-queueing)
        $result = $service->executeMethod($model, $method, $this->parameters);

        // Log the execution
        // @phpstan-ignore argument.type
        $service->logExecution($model, $method, $this->parameters, $result, $user);
    }
}
