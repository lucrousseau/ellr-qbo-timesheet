<?php

namespace App\Services;

use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use QuickBooksOnline\API\DataService\DataService;

class QuickBooksService
{
    private const OAUTH_STATE_TTL_MINUTES = 10;

    public function dataService(?QuickBooksToken $token = null): DataService
    {
        $config = [
            'auth_mode' => 'oauth2',
            'ClientID' => config('quickbooks.client_id'),
            'ClientSecret' => config('quickbooks.client_secret'),
            'RedirectURI' => config('quickbooks.redirect_uri'),
            'scope' => config('quickbooks.scope'),
            'baseUrl' => config('quickbooks.base_url'),
        ];

        $dataService = DataService::Configure($config);

        if ($token) {
            $dataService->updateOAuth2Token($token->toOAuth2AccessToken());
        }

        return $dataService;
    }

    public function oauthHelper(?string $state = null): OAuth2LoginHelper
    {
        return new OAuth2LoginHelper(
            config('quickbooks.client_id'),
            config('quickbooks.client_secret'),
            config('quickbooks.redirect_uri'),
            config('quickbooks.scope'),
            $state,
        );
    }

    public function createAuthorizationState(User $user): string
    {
        $state = Str::random(40);

        Cache::put(
            $this->oauthStateCacheKey($state),
            ['user_id' => $user->id],
            now()->addMinutes(self::OAUTH_STATE_TTL_MINUTES),
        );

        return $state;
    }

    public function authorizationUrl(string $state): string
    {
        return $this->oauthHelper($state)->getAuthorizationCodeURL();
    }

    /**
     * @return array{user_id: int}
     */
    public function consumeAuthorizationState(string $state): array
    {
        $cacheKey = $this->oauthStateCacheKey($state);
        $payload = Cache::pull($cacheKey);

        if (! is_array($payload) || ! isset($payload['user_id'])) {
            throw new QuickBooksException('Invalid or expired OAuth state.');
        }

        return $payload;
    }

    public function exchangeCode(string $code, string $realmId, User $user): QuickBooksToken
    {
        $oauthHelper = $this->oauthHelper();
        $accessToken = $oauthHelper->exchangeAuthorizationCodeForToken($code, $realmId);

        if ($error = $oauthHelper->getLastError()) {
            throw new QuickBooksException(
                'QuickBooks token exchange failed.',
                $error->getResponseBody(),
            );
        }

        $token = QuickBooksToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'realm_id' => $realmId,
            ],
            [
                'access_token' => $accessToken->getAccessToken(),
                'refresh_token' => $accessToken->getRefreshToken(),
                'access_token_expires_at' => $accessToken->getAccessTokenExpiresAt(),
                'refresh_token_expires_at' => $accessToken->getRefreshTokenExpiresAt(),
            ],
        );

        QuickBooksToken::query()
            ->where('user_id', $user->id)
            ->whereKeyNot($token->id)
            ->delete();

        return $token;
    }

    public function refreshToken(QuickBooksToken $token): QuickBooksToken
    {
        return Cache::lock("quickbooks_refresh:{$token->id}", 30)->block(10, function () use ($token) {
            $token->refresh();

            if (! $token->isAccessTokenExpired()) {
                return $token;
            }

            $dataService = $this->dataService($token);
            $accessToken = $dataService->getOAuth2LoginHelper()->refreshToken();

            if ($accessToken === null) {
                throw new QuickBooksException('QuickBooks token refresh failed.');
            }

            if ($error = $dataService->getLastError()) {
                throw new QuickBooksException(
                    'QuickBooks token refresh failed.',
                    $error->getResponseBody(),
                );
            }

            $token->update([
                'access_token' => $accessToken->getAccessToken(),
                'refresh_token' => $accessToken->getRefreshToken(),
                'access_token_expires_at' => $accessToken->getAccessTokenExpiresAt(),
                'refresh_token_expires_at' => $accessToken->getRefreshTokenExpiresAt(),
            ]);

            return $token->fresh();
        });
    }

    public function disconnect(User $user): void
    {
        QuickBooksToken::query()
            ->where('user_id', $user->id)
            ->delete();
    }

    private function oauthStateCacheKey(string $state): string
    {
        return "quickbooks_oauth_state:{$state}";
    }
}
