<?php

declare(strict_types=1);

namespace Blafast\Foundation\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Password reset notification.
 *
 * Sends email-only notification (no database storage for security).
 * Includes password reset link and expiry information.
 */
class PasswordResetNotification extends BlaFastNotification
{
    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly string $token,
        private readonly string $email,
    ) {
        parent::__construct();
    }

    /**
     * Get the notification's delivery channels.
     *
     * Only email channel for security - we don't want password reset
     * tokens stored in the database notifications table.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = url(config('app.frontend_url').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $this->email,
        ]));

        $expiryMinutes = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Password Reset Request')
            ->markdown('blafast-fundation::emails.password-reset', [
                'resetUrl' => $resetUrl,
                'expiryMinutes' => $expiryMinutes,
            ]);
    }
}
