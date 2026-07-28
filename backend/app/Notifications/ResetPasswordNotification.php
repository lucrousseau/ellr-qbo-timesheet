<?php

/**
 * Password reset notification with a frontend reset link.
 */

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sends a password reset link that opens a frontend reset screen.
 */
class ResetPasswordNotification extends ResetPassword
{
    /**
     * Optional frontend base URL override for the reset deep link.
     */
    public ?string $frontendUrl = null;

    /**
     * @param  string  $token  Password reset token.
     * @param  string|null  $frontendUrl  Optional frontend base URL override.
     */
    public function __construct(string $token, ?string $frontendUrl = null)
    {
        parent::__construct($token);
        $this->frontendUrl = $frontendUrl;
    }

    /**
     * Builds the frontend URL that collects the new password.
     *
     * @param  object  $notifiable  User receiving the notification.
     * @return string
     */
    protected function resetUrl($notifiable): string
    {
        $frontendUrl = rtrim(
            (string) ($this->frontendUrl ?? config('app.frontend_auth_url')),
            '/',
        );
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
            ->subject(__('mail.reset.subject'))
            ->line(__('mail.reset.line'))
            ->action(__('mail.reset.action'), $this->resetUrl($notifiable))
            ->line(__('mail.reset.footer'));
    }
}
