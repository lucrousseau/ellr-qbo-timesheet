<?php

/**
 * Password reset notification with a frontend reset link.
 */

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sends a password reset link that opens the timesheet frontend reset screen.
 */
class ResetPasswordNotification extends ResetPassword
{
    /**
     * Builds the frontend URL that collects the new password.
     *
     * @param  object  $notifiable  User receiving the notification.
     * @return string
     */
    protected function resetUrl($notifiable): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_auth_url'), '/');
        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return "{$frontendUrl}/reset-password?{$query}";
    }

    /**
     * Formats the password reset email message.
     *
     * @param  object  $notifiable  User receiving the notification.
     * @return MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your password')
            ->line('You are receiving this email because we received a password reset request for your account.')
            ->action('Reset password', $this->resetUrl($notifiable))
            ->line('If you did not request a password reset, no further action is required.');
    }
}
