<?php

declare(strict_types=1);

namespace Blafast\Foundation\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Notification sent to Superadmins when a job fails.
 *
 * This notification is sent via email and database channels to alert
 * superadmins about critical job failures that require attention.
 */
class JobFailedNotification extends BlaFastNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly string $jobClass,
        private readonly string $errorMessage,
        private readonly ?string $jobOrganizationId,
    ) {
        // Don't call parent constructor - this is a system notification
        // that doesn't need organization context from current request
        $this->onQueue(config('blafast-fundation.queue.names.notifications', 'notifications'));
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $jobName = class_basename($this->jobClass);
        $errorPreview = Str::limit($this->errorMessage, 500);

        return (new MailMessage)
            ->subject("[BlaFast] Job Failed: {$jobName}")
            ->error()
            ->line('A queued job has failed after maximum retry attempts.')
            ->line("**Job:** {$this->jobClass}")
            ->line("**Error:** {$errorPreview}")
            ->line('**Organization:** '.($this->jobOrganizationId ?? 'Global'))
            ->line('Please investigate the failed job and take appropriate action.')
            ->action('View Failed Jobs', url('/admin/failed-jobs'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'job_failed',
            'title' => 'Job Failed',
            'message' => "Job {$this->jobClass} failed: {$this->errorMessage}",
            'job_class' => $this->jobClass,
            'organization_id' => $this->jobOrganizationId,
            'severity' => 'error',
        ];
    }
}
