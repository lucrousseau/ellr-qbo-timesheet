<?php

namespace App\Models;

use Database\Factories\QuickBooksTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;

/**
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

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'access_token_expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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

    public function isAccessTokenExpired(): bool
    {
        if ($this->access_token_expires_at === null) {
            return false;
        }

        return $this->access_token_expires_at->isPast();
    }
}
