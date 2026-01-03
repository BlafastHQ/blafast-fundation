<?php

declare(strict_types=1);

namespace Blafast\Foundation\Notifications;

use Blafast\Foundation\Services\OrganizationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Base notification class for BlaFast notifications.
 *
 * All BlaFast notifications should extend this class to ensure:
 * - Notifications are queued for background processing
 * - Organization context is included in notification data
 * - Consistent notification structure across the application
 */
abstract class BlaFastNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Organization ID for this notification.
     */
    protected ?string $organizationId = null;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        // Capture organization context at notification creation time
        $context = app(OrganizationContext::class);
        if ($context->hasContext()) {
            $this->organizationId = $context->id();
        }
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    abstract public function via(object $notifiable): array;

    /**
     * Get the array representation of the notification.
     *
     * This is used for database channel storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'organization_id' => $this->organizationId,
        ];
    }

    /**
     * Get the organization ID for this notification.
     */
    public function getOrganizationId(): ?string
    {
        return $this->organizationId;
    }
}
