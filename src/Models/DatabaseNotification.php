<?php

declare(strict_types=1);

namespace Blafast\Foundation\Models;

use Blafast\Foundation\Scopes\DatabaseNotificationOrganizationScope;
use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

/**
 * Custom DatabaseNotification model with organization scoping.
 *
 * Extends Laravel's base DatabaseNotification to add multi-tenant support.
 * All notifications are automatically scoped to the current organization context.
 *
 * @property string $id
 * @property string $type
 * @property string $notifiable_type
 * @property string $notifiable_id
 * @property string|null $organization_id
 * @property array $data
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Organization|null $organization
 */
class DatabaseNotification extends BaseDatabaseNotification
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'notifications';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        parent::booted();

        // Apply organization scoping
        static::addGlobalScope(new DatabaseNotificationOrganizationScope);

        // Automatically set organization_id when creating notification
        static::creating(function (DatabaseNotification $notification) {
            if (empty($notification->organization_id)) {
                $context = app(OrganizationContext::class);
                if ($context->hasContext()) {
                    $notification->organization_id = $context->id();
                }
            }
        });
    }

    /**
     * Get the organization that owns the notification.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
