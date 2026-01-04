<?php

declare(strict_types=1);

namespace Blafast\Foundation\Jobs;

use Blafast\Foundation\Models\DeferredApiRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * ProcessDeferredApiRequest Job
 *
 * Executes a deferred API request by making an internal HTTP call
 * and storing the result.
 */
class ProcessDeferredApiRequest extends BlaFastJob
{
    /**
     * Create a new job instance.
     */
    public function __construct(
        public DeferredApiRequest $deferredRequest
    ) {
        parent::__construct();

        // Override tries and timeout from request config
        $this->tries = $deferredRequest->max_attempts;
        $this->timeout = 300; // 5 minutes default
        $this->onQueue($this->resolveQueue());
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Mark as processing
        $this->deferredRequest->markAsProcessing();

        try {
            // Execute the internal request
            $response = $this->executeInternalRequest();

            // Mark as completed with result
            $this->deferredRequest->markAsCompleted(
                result: $response->json(),
                statusCode: $response->status()
            );
        } catch (Throwable $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Execute the internal HTTP request.
     */
    protected function executeInternalRequest(): \Illuminate\Http\Client\Response
    {
        $request = Http::withHeaders($this->buildHeaders())
            ->timeout($this->timeout);

        $url = $this->buildInternalUrl();
        $method = strtolower($this->deferredRequest->http_method);

        return match ($method) {
            'get' => $request->get($url, $this->deferredRequest->query_params ?? []),
            'post' => $request->post($url, $this->deferredRequest->payload ?? []),
            'put' => $request->put($url, $this->deferredRequest->payload ?? []),
            'patch' => $request->patch($url, $this->deferredRequest->payload ?? []),
            'delete' => $request->delete($url, $this->deferredRequest->payload ?? []),
            default => throw new \RuntimeException("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * Build headers for internal request.
     */
    protected function buildHeaders(): array
    {
        return array_merge(
            $this->deferredRequest->headers ?? [],
            [
                'X-Deferred-Request-Id' => $this->deferredRequest->id,
                'X-Deferred-Execution' => 'true',
            ]
        );
    }

    /**
     * Build internal URL for request.
     */
    protected function buildInternalUrl(): string
    {
        return config('app.url').'/'.ltrim($this->deferredRequest->endpoint, '/');
    }

    /**
     * Resolve queue name based on priority.
     */
    protected function resolveQueue(): string
    {
        return match ($this->deferredRequest->priority) {
            'high' => 'deferred-high',
            'low' => 'deferred-low',
            default => 'deferred',
        };
    }

    /**
     * Handle job failure during execution.
     */
    protected function handleFailure(Throwable $e): void
    {
        $this->deferredRequest->markAsFailed(
            errorCode: 'EXECUTION_ERROR',
            errorMessage: $e->getMessage()
        );
    }

    /**
     * Handle job failure after all retries exhausted.
     */
    public function failed(Throwable $exception): void
    {
        $this->deferredRequest->markAsFailed(
            errorCode: 'JOB_FAILED',
            errorMessage: $exception->getMessage()
        );
    }
}
