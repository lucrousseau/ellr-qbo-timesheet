<?php

/**
 * Authenticated application user with optional QuickBooks employee mapping.
 */

namespace App\Models;

use App\Enums\UserLocale;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\PasswordResetLinkService;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'locale', 'qbo_employee_ref', 'qbo_employee_name'])]
#[Hidden(['password', 'remember_token', 'is_admin'])]
/**
 * Eloquent user with Sanctum auth and optional QBO employee fields.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Indicates whether the user has administrator privileges.
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /**
     * Returns the supported locale stored on the user, defaulting to English.
     *
     * @return string
     */
    public function preferredLocale(): string
    {
        $locale = $this->locale ?? UserLocale::En->value;

        return in_array($locale, UserLocale::values(), true)
            ? $locale
            : UserLocale::En->value;
    }

    /**
     * Relationship to the most recent QuickBooks OAuth token.
     *
     * @return HasOne<QuickBooksToken, $this>
     */
    public function quickBooksToken(): HasOne
    {
        return $this->hasOne(QuickBooksToken::class)->latestOfMany();
    }

    /**
     * Sends the custom email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(
            (new VerifyEmailNotification)->locale($this->preferredLocale()),
        );
    }

    /**
     * Sends the custom password reset notification (default frontend auth URL).
     *
     * Forgot-password API requests use {@see PasswordResetLinkService} for per-app deep links.
     *
     * @param  string  $token  Password reset token.
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(
            (new ResetPasswordNotification(
                $token,
                app(PasswordResetLinkService::class)->resolveFrontendUrl(null),
            ))->locale($this->preferredLocale()),
        );
    }
}
