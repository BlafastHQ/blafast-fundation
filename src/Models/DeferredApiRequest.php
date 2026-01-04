<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use Blafast\Foundation\Enums\DeferredRequestStatus;
use Blafast\Foundation\Traits\BelongsToOrganization;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Deferred API Request Model
 *
 * Stores deferred API requests and their execution results.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $user_id
 * @property string $http_method
 * @property string $endpoint
 * @property array|null $payload
 * @property array|null $query_params
 * @property array $headers
 * @property DeferredRequestStatus $status
 * @property int|null $progress
 * @property string|null $progress_message
 * @property array|null $result
 * @property int|null $result_status_code
 * @property string|null $error_code
 * @property string|null $error_message
 * @property int $attempts
 * @property int $max_attempts
 * @property string $priority
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class DeferredApiRequest extends Model
{
    use HasFactory;
    use BelongsToOrganization;
    use HasUuids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'http_method',
        'endpoint',
        'payload',
        'query_params',
        'headers',
        'status',
        'progress',
        'progress_message',
        'result',
        'result_status_code',
        'error_code',
        'error_message',
        'attempts',
        'max_attempts',
        'priority',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'encrypted:json',
        'query_params' => 'json',
        'headers' => 'encrypted:json',
        'result' => 'encrypted:json',
        'status' => DeferredRequestStatus::class,
        'progress' => 'integer',
        'result_status_code' => 'integer',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that created this request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Get the organization that owns this request.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope to filter requests for a specific user.
     */
    public function scopeForUser(Builder $query, Authenticatable $user): Builder
    {
        // @phpstan-ignore property.notFound
        return $query->where('user_id', $user->id);
    }

    /**
     * Scope to filter pending requests.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DeferredRequestStatus::Pending);
    }

    /**
     * Scope to filter expired requests.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Check if request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === DeferredRequestStatus::Pending;
    }

    /**
     * Check if request is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === DeferredRequestStatus::Processing;
    }

    /**
     * Check if request is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === DeferredRequestStatus::Completed;
    }

    /**
     * Check if request failed.
     */
    public function isFailed(): bool
    {
        return $this->status === DeferredRequestStatus::Failed;
    }

    /**
     * Check if request can be retried.
     */
    public function canRetry(): bool
    {
        return $this->isFailed() && $this->attempts < $this->max_attempts;
    }

    /**
     * Mark request as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => DeferredRequestStatus::Processing,
            'started_at' => now(),
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * Mark request as completed.
     */
    public function markAsCompleted(mixed $result, int $statusCode): void
    {
        $this->update([
            'status' => DeferredRequestStatus::Completed,
            'completed_at' => now(),
            'result' => $result,
            'result_status_code' => $statusCode,
        ]);
    }

    /**
     * Mark request as failed.
     */
    public function markAsFailed(string $errorCode, string $errorMessage): void
    {
        $this->update([
            'status' => DeferredRequestStatus::Failed,
            'completed_at' => now(),
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark request as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => DeferredRequestStatus::Cancelled,
            'completed_at' => now(),
        ]);
    }

    /**
     * Update request progress.
     */
    public function updateProgress(int $percentage, ?string $message = null): void
    {
        $this->update([
            'progress' => min(100, max(0, $percentage)),
            'progress_message' => $message,
        ]);
    }
}
