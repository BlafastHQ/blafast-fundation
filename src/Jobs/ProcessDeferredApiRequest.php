<?php

declare(strict_types=1);

namespace Blafast\Foundation\Jobs;

use Blafast\Foundation\Models\DeferredApiRequest;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * ProcessDeferredApiRequest Job
 *
 * Executes a deferred API request IN PROCESS as the original user, with the
 * organization context resolved by the real middleware stack (task 15/C2: the
 * old implementation replayed over real HTTP with the Authorization header
 * stripped, so every auth:sanctum target answered 401 — which was then stored
 * as a *successful* result).
 *
 * Outcome contract (task 15/H10):
 *  - 2xx  → completed (result + status stored)
 *  - 4xx  → failed, terminal (client errors do not heal on retry)
 *  - 5xx / transport exceptions → retried per max_attempts with the queue's
 *    backoff; only after the last attempt is the request marked failed.
 */
class ProcessDeferredApiRequest extends BlaFastJob
{
    /**
     * Last non-2xx response observed, for failed() after retries exhaust.
     *
     * @var array{status: int, body: mixed}|null
     */
    protected ?array $lastErrorResponse = null;

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
        $deferred = $this->deferredRequest;

        $deferred->markAsProcessing();

        $user = $deferred->user;

        if ($user === null) {
            // Terminal: the owning user is gone; a retry cannot help.
            $deferred->markAsFailed(
                errorCode: 'USER_GONE',
                errorMessage: 'The user that created this deferred request no longer exists.'
            );

            return;
        }

        try {
            $response = $this->executeInProcess($user);
        } finally {
            // Never leak the request's identity or context into the next job.
            auth()->forgetGuards();
            organization_context()->clear();
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getContent(), true);

        if ($status >= 200 && $status < 300) {
            $deferred->markAsCompleted(result: $body, statusCode: $status);

            return;
        }

        if ($status >= 500 && $deferred->fresh()->attempts < $this->tries) {
            // Retryable: let the queue's retry/backoff machinery run (H10 — the
            // old blanket catch made failed() unreachable and killed retries).
            $this->lastErrorResponse = ['status' => $status, 'body' => $body];

            throw new \RuntimeException("Deferred execution returned HTTP {$status}; retrying.");
        }

        $deferred->markAsFailed(
            errorCode: "HTTP_{$status}",
            errorMessage: is_array($body) ? json_encode($body) : (string) $response->getContent(),
            statusCode: $status,
            result: $body,
        );
    }

    /**
     * Dispatch the stored request through the HTTP kernel as the original user.
     * The real middleware stack runs: auth:sanctum sees the pre-authenticated
     * user, org.resolve validates membership and restores the organization
     * context (and spatie team id), and the `deferred` middleware's loop guard
     * sees the X-Deferred-Execution marker.
     */
    protected function executeInProcess(object $user): Response
    {
        $deferred = $this->deferredRequest;

        $server = [];

        foreach ($deferred->headers ?? [] as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', (string) $name))] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        $server['HTTP_ACCEPT'] = 'application/json';
        $server['CONTENT_TYPE'] = 'application/json';
        $server['HTTP_X_DEFERRED_REQUEST_ID'] = $deferred->id;
        $server['HTTP_X_DEFERRED_EXECUTION'] = 'true';

        if ($deferred->organization_id !== null) {
            $server['HTTP_X_ORGANIZATION_ID'] = $deferred->organization_id;
        }

        $method = strtoupper($deferred->http_method);
        $uri = '/'.ltrim($deferred->endpoint, '/');

        if ($method === 'GET' && ! empty($deferred->query_params)) {
            $uri .= (str_contains($uri, '?') ? '&' : '?').http_build_query($deferred->query_params);
        }

        $content = $method === 'GET' ? null : json_encode($deferred->payload ?? []);

        $request = Request::createFromBase(SymfonyRequest::create(
            config('app.url').$uri,
            $method,
            [],
            [],
            [],
            $server,
            $content
        ));

        // Pre-authenticate the sanctum guard for this in-process cycle.
        auth()->guard('sanctum')->setUser($user);
        $request->setUserResolver(fn () => $user);

        $kernel = app(HttpKernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        return $response;
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
     * Handle job failure after all retries exhausted (or an unexpected throw).
     */
    public function failed(Throwable $exception): void
    {
        $this->deferredRequest->markAsFailed(
            errorCode: $this->lastErrorResponse !== null ? 'HTTP_'.$this->lastErrorResponse['status'] : 'JOB_FAILED',
            errorMessage: $exception->getMessage(),
            statusCode: $this->lastErrorResponse['status'] ?? null,
            result: $this->lastErrorResponse['body'] ?? null,
        );
    }
}
