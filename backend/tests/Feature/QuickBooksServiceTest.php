<?php

use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use QuickBooksOnline\API\DataService\DataService;

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

    expect($dataService)->toBeInstanceOf(DataService::class);
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
    $dataService = app(QuickBooksService::class)->dataService($token);

    expect($dataService)->toBeInstanceOf(DataService::class);
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
    $oauthHelper->shouldReceive('refreshToken')->once()->andReturn($accessToken);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('getOAuth2LoginHelper')->once()->andReturn($oauthHelper);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldReceive('dataService')->once()->with(Mockery::on(fn ($arg) => $arg->is($token)))->andReturn($dataService);

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
    $service->shouldNotReceive('dataService');

    $refreshed = $service->refreshToken($token);

    expect($refreshed->access_token)->toBe('fresh-access');
});

it('returns a valid quickbooks token without calling the sdk', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldNotReceive('dataService');

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
    $oauthHelper->shouldReceive('refreshToken')->once()->andReturn($accessToken);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('getOAuth2LoginHelper')->once()->andReturn($oauthHelper);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldReceive('dataService')->once()->andReturn($dataService);

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

it('throws when quickbooks token refresh returns null', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->expired()->create();

    $oauthHelper = Mockery::mock(OAuth2LoginHelper::class);
    $oauthHelper->shouldReceive('refreshToken')->andReturn(null);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('getOAuth2LoginHelper')->andReturn($oauthHelper);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldReceive('dataService')->andReturn($dataService);

    expect(fn () => $service->refreshToken($token))
        ->toThrow(QuickBooksException::class, 'QuickBooks token refresh failed.');
});

it('throws when quickbooks token refresh fails with sdk error', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->expired()->create();

    $accessToken = Mockery::mock(OAuth2AccessToken::class);

    $oauthHelper = Mockery::mock(OAuth2LoginHelper::class);
    $oauthHelper->shouldReceive('refreshToken')->andReturn($accessToken);

    $error = Mockery::mock();
    $error->shouldReceive('getResponseBody')->andReturn('refresh failed');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('getOAuth2LoginHelper')->andReturn($oauthHelper);
    $dataService->shouldReceive('getLastError')->andReturn($error);

    $service = Mockery::mock(QuickBooksService::class)->makePartial();
    $service->shouldReceive('dataService')->andReturn($dataService);

    expect(fn () => $service->refreshToken($token))
        ->toThrow(QuickBooksException::class, 'QuickBooks token refresh failed.');
});

afterEach(function () {
    if (! Cache::getFacadeRoot() instanceof MockInterface) {
        Cache::flush();
    }
});
