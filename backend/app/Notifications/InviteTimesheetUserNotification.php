<?php

/**
 * Timesheet onboarding notification with a password-set deep link.
 */

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sends a password-set link that opens the timesheet reset screen.
 */
class InviteTimesheetUserNotification extends ResetPassword
{
    /**
     * Optional frontend base URL override for the password-set deep link.
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
     * Builds the frontend URL that collects the initial password.
     *
     * @param  object  $notifiable  User receiving the notification.
     * @return string
     */
    protected function resetUrl($notifiable): string
    {
        $frontendUrl = rtrim(
            (string) ($this->frontendUrl ?? config('app.frontend_timesheet_url')),
            '/',
        );
        $query = http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return "{$frontendUrl}/reset-password?{$query}";
    }

    /**
     * Formats the timesheet invitation email message.
     *
     * @param  object  $notifiable  User receiving the notification.
     * @return MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.invite.subject'))
            ->line(__('mail.invite.line'))
            ->action(__('mail.invite.action'), $this->resetUrl($notifiable))
            ->line(__('mail.invite.footer'));
    }
}
