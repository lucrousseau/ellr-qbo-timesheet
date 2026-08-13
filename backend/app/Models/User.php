<?php

/**
 * Authenticated application user with optional QuickBooks employee mapping.
 */

namespace App\Models;

use App\Concerns\BelongsToOrganization;
use App\Enums\UserLocale;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Services\PasswordResetLinkService;
use App\Services\UserLevelResolverService;
use App\Support\PersonName;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['organization_id', 'first_name', 'last_name', 'name', 'email', 'password', 'locale', 'timezone', 'qbo_employee_ref', 'qbo_all_customers_access', 'supervisor_id'])]
#[Hidden(['password', 'remember_token', 'is_admin', 'is_super_admin'])]
/**
 * Eloquent user with Sanctum auth and optional QBO employee fields.
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use BelongsToOrganization, HasApiTokens, HasFactory, Notifiable;

    /**
     * Computed attributes included in array/JSON serialization.
     *
     * @var list<string>
     */
    protected $appends = ['name'];

    /**
     * Bootstraps model lifecycle hooks.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if ($user->user_level_id !== null) {
                return;
            }

            $user->user_level_id = app(UserLevelResolverService::class)->defaultId();
        });

        static::saving(function (self $user): void {
            if ($user->organization_id !== null || $user->isSuperAdmin()) {
                return;
            }

            throw new \InvalidArgumentException('Tenant users require an organization_id.');
        });
    }

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
            'is_super_admin' => 'boolean',
            'qbo_all_customers_access' => 'boolean',
        ];
    }

    /**
     * Full display name derived from first and last name columns.
     *
     * Setting `name` splits the value into first_name and last_name for legacy callers.
     *
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => PersonName::join(
                (string) ($this->attributes['first_name'] ?? ''),
                (string) ($this->attributes['last_name'] ?? ''),
            ),
            set: function (string $value): array {
                [$firstName, $lastName] = PersonName::split($value);

                return [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ];
            },
        );
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
     * Indicates whether the user has platform super-administrator privileges.
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
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
     * Relationship to the tenant organization that owns this account.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Relationship to the permission tier assigned to this account.
     *
     * @return BelongsTo<UserLevel, $this>
     */
    public function userLevel(): BelongsTo
    {
        return $this->belongsTo(UserLevel::class);
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
     * Relationship to the supervisor who approves this user's time entries.
     *
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    /**
     * Relationship to employees supervised by this user.
     *
     * @return HasMany<User, $this>
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    /**
     * Relationship to administrator-assigned QuickBooks customers.
     *
     * @return HasMany<UserQboCustomer, $this>
     */
    public function qboCustomers(): HasMany
    {
        return $this->hasMany(UserQboCustomer::class);
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

    /**
     * Resolves route parameters to users in the authenticated actor's organization.
     *
     * @param  mixed  $value  Route key value.
     * @param  string|null  $field  Route key field name.
     * @return User
     */
    public function resolveRouteBinding($value, $field = null): User
    {
        $field ??= $this->getRouteKeyName();
        $query = $this->newQuery()->where($field, $value);

        $actor = auth()->user();

        if (! $actor instanceof self) {
            abort(404);
        }

        return $query
            ->where('organization_id', $actor->organization_id)
            ->firstOrFail();
    }
}
