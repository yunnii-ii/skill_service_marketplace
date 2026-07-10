<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expiresIn = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Reset your password')
            ->line('Use this reset token to change your password.')
            ->line('Reset token: '.$this->token)
            ->line('This token will expire in '.$expiresIn.' minutes.')
            ->line('If you did not request a password reset, you can ignore this email.');
    }
}
