<?php

declare(strict_types=1);

namespace Blafast\Foundation\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notification sent to Superadmins when a scheduled task fails.
 *
 * This notification is triggered by the scheduler when a task
 * completes with a non-zero exit code.
 */
class ScheduledTaskFailedNotification extends BlaFastNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        private string $taskDescription
    ) {
        parent::__construct();
        $this->onQueue('notifications');
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
        return (new MailMessage)
            ->subject('[BlaFast] Scheduled Task Failed')
            ->error()
            ->line('A scheduled task has failed.')
            ->line("**Task:** {$this->taskDescription}")
            ->line('**Time:** '.now()->toDateTimeString())
            ->action('View Logs', url('/admin/logs'));
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    protected function databasePayload(object $notifiable): array
    {
        return [
            'type' => 'scheduled_task_failed',
            'title' => 'Scheduled Task Failed',
            'message' => "Task '{$this->taskDescription}' failed",
            'task_description' => $this->taskDescription,
            'failed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get the template name for the notification.
     */
    protected function getTemplateName(): string
    {
        return 'scheduled-task-failed';
    }
}
