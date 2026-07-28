<?php

use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Exception\SdkException;
use QuickBooksOnline\API\Exception\ServiceException;

covers(QuickBooksService::class);

it('stores oauth state for a user', function () {
    $user = User::factory()->create();
    $service = app(QuickBooksService::class);

    $state = $service->createAuthorizationState($user);

    expect($state)->toHaveLength(40)
        ->and(Cache::get("quickbooks_oauth_state:{$state}"))->toBe(['user_id' => $user->id]);
});

it('builds an authorization url with state', function () {
    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $oauthHelper = Mockery::mock(OAuth2LoginHelper::class);
    $oauthHelper->shouldReceive('getAuthorizationCodeURL')
        ->once()
        ->andReturn('https://appcenter.intuit.com/connect/oauth2');

    $service->shouldReceive('oauthHelper')->once()->with('state-123')->andReturn($oauthHelper);

    expect($service->authorizationUrl('state-123'))
        ->toBe('https://appcenter.intuit.com/connect/oauth2');
});

it('configures a quickbooks data service without a token', function () {
    config([
        'quickbooks.client_id' => 'client-id',
        'quickbooks.client_secret' => 'client-secret',
        'quickbooks.redirect_uri' => 'http://localhost/callback',
        'quickbooks.scope' => 'com.intuit.quickbooks.accounting',
        'quickbooks.base_url' => 'development',
    ]);

    $dataService = app(QuickBooksService::class)->dataService();

    expect($dataService)->toBeInstanceOf(DataService::class)
        ->and($dataService->getServiceContext()->baseserviceURL)
        ->toBe('https://sandbox-quickbooks.api.intuit.com/v3/');
});

it('applies quickbooks oauth configuration to the oauth helper', function () {
    config([
        'quickbooks.client_id' => 'configured-client-id',
        'quickbooks.client_secret' => 'client-secret',
        'quickbooks.redirect_uri' => 'http://localhost/oauth/callback',
        'quickbooks.scope' => 'com.intuit.quickbooks.accounting',
        'quickbooks.base_url' => 'development',
    ]);

    $url = app(QuickBooksService::class)->oauthHelper('state-abc')->getAuthorizationCodeURL();

    expect($url)
        ->toContain('client_id=configured-client-id')
        ->and($url)->toContain('scope=com.intuit.quickbooks.accounting')
        ->and($url)->toContain('redirect_uri='.urlencode('http://localhost/oauth/callback'))
        ->and($url)->toContain('state=state-abc');
});

it('configures a quickbooks data service with a token', function () {
    config([
        'quickbooks.client_id' => 'client-id',
        'quickbooks.client_secret' => 'client-secret',
        'quickbooks.redirect_uri' => 'http://localhost/callback',
        'quickbooks.scope' => 'com.intuit.quickbooks.accounting',
        'quickbooks.base_url' => 'development',
    ]);

    $token = QuickBooksToken::factory()->forUser(User::factory()->create())->create();
    $oauthToken = Mockery::mock(OAuth2AccessToken::class);
    $oauthToken->shouldReceive('getRealmID')->andReturn($token->realm_id);
    $tokenSpy = Mockery::mock($token)->makePartial();
    $tokenSpy->shouldReceive('toOAuth2AccessToken')->once()->andReturn($oauthToken);

    $dataService = app(QuickBooksService::class)->dataService($tokenSpy);

    expect($dataService)->toBeInstanceOf(DataService::class)
        ->and($dataService->getOAuth2LoginHelper())->toBeInstanceOf(OAuth2LoginHelper::class);
});

it('consumes oauth state only once', function () {
    Cache::put('quickbooks_oauth_state:test-state', ['user_id' => 42], now()->addMinutes(10));
    $service = app(QuickBooksService::class);

    expect($service->consumeAuthorizationState('test-state'))->toBe(['user_id' => 42]);

    expect(fn () => $service->consumeAuthorizationState('test-state'))
        ->toThrow(QuickBooksException::class);
});

it('throws when oauth state payload is invalid', function () {
    Cache::put('quickbooks_oauth_state:invalid', ['missing' => 'user'], now()->addMinutes(10));
    $service = app(QuickBooksService::class);

    expect(fn () => $service->consumeAuthorizationState('invalid'))
        ->toThrow(QuickBooksException::class, 'Invalid or expired OAuth state.');
});

it('throws when oauth state is unknown', function () {
    $service = app(QuickBooksService::class);

    expect(fn () => $service->consumeAuthorizationState('missing'))
        ->toThrow(QuickBooksException::class, 'Invalid or expired OAuth state.');
});

it('removes stale quickbooks tokens when connecting a new realm', function () {
    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'old-realm']);

    $accessToken = Mockery::mock(OAuth2AccessToken::class);
    $accessToken->shouldReceive('getAccessToken')->andReturn('access');
    $accessToken->shouldReceive('getRefreshToken')->andReturn('refresh');
    $accessToken->shouldReceive('getAccessTokenExpiresAt')->andReturn(now()->addHour());
    $accessToken->shouldReceive('getRefreshTokenExpiresAt')->andReturn(now()->addDays(100));

    $oauthHelper = Mockery::mock(OAuth2LoginHelper::class);
    $oauthHelper->shouldReceive('exchangeAuthorizationCodeForToken')->andReturn($accessToken);
    $oauthHelper->shouldReceive('getLastError')->andReturn(null);

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldReceive('oauthHelper')->andReturn($oauthHelper);

    $token = $service->exchangeCode('code', 'new-realm', $user);

    expect($token->realm_id)->toBe('new-realm')
        ->and(QuickBooksToken::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(QuickBooksToken::query()->where('user_id', $user->id)->value('realm_id'))->toBe('new-realm');
});

it('throws when quickbooks token exchange fails', function () {
    $user = User::factory()->create();

    $oauthHelper = Mockery::mock(OAuth2LoginHelper::class);
    $error = Mockery::mock();
    $error->shouldReceive('getResponseBody')->andReturn('exchange failed');
    $oauthHelper->shouldReceive('exchangeAuthorizationCodeForToken')->andReturn(Mockery::mock(OAuth2AccessToken::class));
    $oauthHelper->shouldReceive('getLastError')->andReturn($error);

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldReceive('oauthHelper')->andReturn($oauthHelper);

    expect(fn () => $service->exchangeCode('code', 'realm', $user))
        ->toThrow(QuickBooksException::class, 'QuickBooks token exchange failed.');
});

it('refreshes an expired quickbooks token', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->expired()->create([
        'access_token' => 'old-access',
        'refresh_token' => 'old-refresh',
    ]);

    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')
        ->once()
        ->with(10, Mockery::type('Closure'))
        ->andReturnUsing(fn ($timeout, $callback) => $callback());

    Cache::shouldReceive('lock')
        ->once()
        ->with("quickbooks_refresh:{$token->id}", 30)
        ->andReturn($lock);

    $accessToken = Mockery::mock(OAuth2AccessToken::class);
    $accessToken->shouldReceive('getAccessToken')->andReturn('new-access');
    $accessToken->shouldReceive('getRefreshToken')->andReturn('new-refresh');
    $accessToken->shouldReceive('getAccessTokenExpiresAt')->andReturn(now()->addHour());
    $accessToken->shouldReceive('getRefreshTokenExpiresAt')->andReturn(now()->addDays(100));

    $oauthHelper = Mockery::mock(OAuth2LoginHelper::class);
    $oauthHelper->shouldReceive('refreshAccessTokenWithRefreshToken')
        ->once()
        ->with('old-refresh')
        ->andReturn($accessToken);

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldReceive('oauthHelper')->once()->andReturn($oauthHelper);

    $refreshed = $service->refreshToken($token);

    expect($refreshed->access_token)->toBe('new-access')
        ->and($refreshed->refresh_token)->toBe('new-refresh');
});

it('reloads the token from the database before checking expiry', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->expired()->create([
        'access_token' => 'stale-access',
    ]);

    QuickBooksToken::query()->findOrFail($token->id)->update([
        'access_token' => 'fresh-access',
        'access_token_expires_at' => now()->addHour(),
    ]);

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldNotReceive('oauthHelper');

    $refreshed = $service->refreshToken($token);

    expect($refreshed->access_token)->toBe('fresh-access');
});

it('returns a valid quickbooks token without calling the sdk', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldNotReceive('oauthHelper');

    $refreshed = $service->refreshToken($token);

    expect($refreshed->is($token))->toBeTrue();
});

it('persists refreshed token expiry timestamps', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->expired()->create();

    $accessExpires = now()->addHours(2);
    $refreshExpires = now()->addDays(50);

    $accessToken = Mockery::mock(OAuth2AccessToken::class);
    $accessToken->shouldReceive('getAccessToken')->andReturn('new-access');
    $accessToken->shouldReceive('getRefreshToken')->andReturn('new-refresh');
    $accessToken->shouldReceive('getAccessTokenExpiresAt')->andReturn($accessExpires);
    $accessToken->shouldReceive('getRefreshTokenExpiresAt')->andReturn($refreshExpires);

    $oauthHelper = Mockery::mock(OAuth2LoginHelper::class);
    $oauthHelper->shouldReceive('refreshAccessTokenWithRefreshToken')
        ->once()
        ->with($token->refresh_token)
        ->andReturn($accessToken);

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldReceive('oauthHelper')->once()->andReturn($oauthHelper);

    $refreshed = $service->refreshToken($token);

    expect($refreshed->access_token_expires_at?->format('Y-m-d H:i:s'))
        ->toBe($accessExpires->format('Y-m-d H:i:s'))
        ->and($refreshed->refresh_token_expires_at?->format('Y-m-d H:i:s'))
        ->toBe($refreshExpires->format('Y-m-d H:i:s'));
});

it('persists token expiry timestamps when exchanging authorization code', function () {
    $user = User::factory()->create();
    $accessExpires = now()->addHour();
    $refreshExpires = now()->addDays(100);

    $accessToken = Mockery::mock(OAuth2AccessToken::class);
    $accessToken->shouldReceive('getAccessToken')->andReturn('access');
    $accessToken->shouldReceive('getRefreshToken')->andReturn('refresh');
    $accessToken->shouldReceive('getAccessTokenExpiresAt')->andReturn($accessExpires);
    $accessToken->shouldReceive('getRefreshTokenExpiresAt')->andReturn($refreshExpires);

    $oauthHelper = Mockery::mock(OAuth2LoginHelper::class);
    $oauthHelper->shouldReceive('exchangeAuthorizationCodeForToken')->andReturn($accessToken);
    $oauthHelper->shouldReceive('getLastError')->andReturn(null);

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldReceive('oauthHelper')->andReturn($oauthHelper);

    $token = $service->exchangeCode('code', 'realm-42', $user);

    expect($token->access_token_expires_at?->format('Y-m-d H:i:s'))
        ->toBe($accessExpires->format('Y-m-d H:i:s'))
        ->and($token->refresh_token_expires_at?->format('Y-m-d H:i:s'))
        ->toBe($refreshExpires->format('Y-m-d H:i:s'));
});

it('throws when oauth state cache entry is not an array', function () {
    Cache::put('quickbooks_oauth_state:scalar', 'not-an-array', now()->addMinutes(10));
    $service = app(QuickBooksService::class);

    expect(fn () => $service->consumeAuthorizationState('scalar'))
        ->toThrow(QuickBooksException::class, 'Invalid or expired OAuth state.');
});

it('builds an oauth helper with optional state', function () {
    config([
        'quickbooks.client_id' => 'client-id',
        'quickbooks.client_secret' => 'client-secret',
        'quickbooks.redirect_uri' => 'http://localhost/callback',
        'quickbooks.scope' => 'com.intuit.quickbooks.accounting',
    ]);

    $helper = app(QuickBooksService::class)->oauthHelper('state-xyz');

    expect($helper)->toBeInstanceOf(OAuth2LoginHelper::class);
});

it('throws when quickbooks token refresh fails with intuit error', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->expired()->create();

    $oauthHelper = Mockery::mock(OAuth2LoginHelper::class);
    $oauthHelper->shouldReceive('refreshAccessTokenWithRefreshToken')
        ->once()
        ->with($token->refresh_token)
        ->andThrow(new ServiceException('Refresh OAuth 2 Access token with Refresh Token failed. Body: [invalid_grant].', 400));

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldReceive('oauthHelper')->once()->andReturn($oauthHelper);

    try {
        $service->refreshToken($token);
        expect(false)->toBeTrue('Expected refresh failure');
    } catch (QuickBooksException $exception) {
        expect($exception->getMessage())->toBe('QuickBooks token refresh failed.')
            ->and($exception->responseBody)->toBe('invalid_grant');
    }
});

it('throws when quickbooks token refresh fails with sdk error', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->expired()->create();

    $oauthHelper = Mockery::mock(OAuth2LoginHelper::class);
    $oauthHelper->shouldReceive('refreshAccessTokenWithRefreshToken')
        ->once()
        ->with($token->refresh_token)
        ->andThrow(new SdkException('SDK refresh failed.'));

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldReceive('oauthHelper')->once()->andReturn($oauthHelper);

    expect(fn () => $service->refreshToken($token))
        ->toThrow(QuickBooksException::class, 'QuickBooks token refresh failed.');
});

it('disconnects quickbooks and deletes local tokens', function () {
    config([
        'quickbooks.client_id' => 'client-id',
        'quickbooks.client_secret' => 'client-secret',
    ]);

    Http::fake([
        'developer.api.intuit.com/*' => Http::response('', 200),
    ]);

    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create([
        'refresh_token' => 'refresh-token-value',
    ]);

    app(QuickBooksService::class)->disconnect($user);

    expect(QuickBooksToken::query()->where('user_id', $user->id)->count())->toBe(0);

    Http::assertSentCount(1);
});

it('disconnects when the user has no quickbooks tokens', function () {
    config([
        'quickbooks.client_id' => 'client-id',
        'quickbooks.client_secret' => 'client-secret',
    ]);

    Http::fake();

    $user = User::factory()->create();

    app(QuickBooksService::class)->disconnect($user);

    Http::assertNothingSent();
    expect(QuickBooksToken::query()->where('user_id', $user->id)->count())->toBe(0);
});

it('still deletes local tokens when intuit revocation fails', function () {
    config([
        'quickbooks.client_id' => 'client-id',
        'quickbooks.client_secret' => 'client-secret',
    ]);

    Http::fake([
        'developer.api.intuit.com/*' => Http::response('error', 400),
    ]);

    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create([
        'refresh_token' => 'refresh-token-value',
    ]);

    app(QuickBooksService::class)->disconnect($user);

    expect(QuickBooksToken::query()->where('user_id', $user->id)->count())->toBe(0);
});

afterEach(function () {
    if (! Cache::getFacadeRoot() instanceof MockInterface) {
        Cache::flush();
    }
});
