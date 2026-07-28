<?php

/**
 * Email verification notification with a signed API verification link.
 */

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

/**
 * Sends a signed link that verifies the user email through the Laravel API.
 */
class VerifyEmailNotification extends VerifyEmail
{
    /**
     * Builds the signed verification URL for the API route.
     *
     * @param  object  $notifiable  User receiving the notification.
     * @return string
     */
    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }

    /**
     * Formats the verification email message.
     *
     * @param  object  $notifiable  User receiving the notification.
     * @return MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.verify.subject'))
            ->line(__('mail.verify.line'))
            ->action(__('mail.verify.action'), $this->verificationUrl($notifiable))
            ->line(__('mail.verify.footer'));
    }
}
