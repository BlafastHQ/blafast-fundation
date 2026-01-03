<?php

declare(strict_types=1);

namespace Blafast\Foundation\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Welcome notification sent to new users.
 *
 * Sends both email and database notifications when a new user is created.
 * Includes personalized welcome message with user's name.
 */
class WelcomeNotification extends BlaFastNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly string $userName,
        private readonly string $userEmail,
    ) {
        parent::__construct();
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
            ->subject('Welcome to BlaFast!')
            ->markdown('blafast-fundation::emails.welcome', [
                'userName' => $this->userName,
                'actionUrl' => url('/'),
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return array_merge(parent::toArray($notifiable), [
            'type' => 'welcome',
            'message' => "Welcome to BlaFast, {$this->userName}!",
            'user_name' => $this->userName,
            'user_email' => $this->userEmail,
        ]);
    }
}
