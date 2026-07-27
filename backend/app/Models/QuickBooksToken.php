<?php

namespace App\Models;

use Database\Factories\QuickBooksTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;

/**
 * Encrypted QuickBooks OAuth token for a user and Intuit realm.
 *
 * @property int $user_id
 * @property Carbon|null $access_token_expires_at
 * @property Carbon|null $refresh_token_expires_at
 */
class QuickBooksToken extends Model
{
    /** @use HasFactory<QuickBooksTokenFactory> */
    use HasFactory;

    protected $table = 'quickbooks_tokens';

    protected $fillable = [
        'user_id',
        'realm_id',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'refresh_token_expires_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Eloquent casts for dates and secret encryption.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
        ];
    }

    /**
     * Owner of the OAuth token.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Converts the model to an OAuth2 token usable by the QuickBooks SDK.
     *
     * @return OAuth2AccessToken
     */
    public function toOAuth2AccessToken(): OAuth2AccessToken
    {
        $token = new OAuth2AccessToken(
            config('quickbooks.client_id'),
            config('quickbooks.client_secret'),
            $this->access_token,
            $this->refresh_token,
        );

        $token->setRealmID($this->realm_id);

        if ($this->access_token_expires_at) {
            $token->setAccessTokenExpiresAt($this->access_token_expires_at->toIso8601String());
        }

        if ($this->refresh_token_expires_at) {
            $token->setRefreshTokenExpiresAt($this->refresh_token_expires_at->toIso8601String());
        }

        return $token;
    }

    /**
     * Indicates whether the access token is expired based on the stored date.
     *
     * @return bool
     */
    public function isAccessTokenExpired(): bool
    {
        if ($this->access_token_expires_at === null) {
            return false;
        }

        return $this->access_token_expires_at->isPast();
    }
}
