<?php

use App\Exceptions\QuickBooksException;
use App\Http\Controllers\Api\QuickBooksEmployeeController;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use App\Services\QuickBooksTokenResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use QuickBooksOnline\API\DataService\DataService;

covers(QuickBooksTokenResolverService::class);
covers(QuickBooksEmployeeController::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'quickbooks.client_id' => 'contract-client-id',
        'quickbooks.client_secret' => 'contract-client-secret',
        'quickbooks.redirect_uri' => 'http://localhost/callback',
        'quickbooks.scope' => 'com.intuit.quickbooks.accounting',
        'quickbooks.base_url' => 'development',
    ]);
});

it('refreshes expired tokens through the oauth helper refresh contract', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->expired()->create([
        'access_token' => 'old-access',
        'refresh_token' => 'old-refresh',
    ]);

    $accessExpires = now()->addHour();
    $refreshExpires = now()->addDays(100);
    $accessToken = Mockery::mock(OAuth2AccessToken::class);
    $accessToken->shouldReceive('getAccessToken')->andReturn('new-access');
    $accessToken->shouldReceive('getRefreshToken')->andReturn('new-refresh');
    $accessToken->shouldReceive('getAccessTokenExpiresAt')->andReturn($accessExpires);
    $accessToken->shouldReceive('getRefreshTokenExpiresAt')->andReturn($refreshExpires);

    $oauthHelper = Mockery::mock(OAuth2LoginHelper::class);
    $oauthHelper->shouldReceive('refreshAccessTokenWithRefreshToken')
        ->once()
        ->with('old-refresh')
        ->andReturn($accessToken);

    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('oauthHelper')->once()->andReturn($oauthHelper);
    $quickBooks->shouldNotReceive('dataService');

    $resolved = (new QuickBooksTokenResolverService($quickBooks))->resolve($user);

    expect($resolved->access_token)->toBe('new-access')
        ->and($resolved->refresh_token)->toBe('new-refresh')
        ->and($resolved->access_token_expires_at?->format('Y-m-d H:i:s'))
        ->toBe($accessExpires->format('Y-m-d H:i:s'))
        ->and(QuickBooksToken::query()->findOrFail($token->id)->access_token)->toBe('new-access');
});

it('refreshes expired quickbooks tokens before listing employees', function () {
    $admin = actingAsAdmin();
    $token = QuickBooksToken::factory()->forUser($admin)->expired()->create(['realm_id' => 'realm-42']);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) [
                'Id' => '42',
                'DisplayName' => 'Jane Doe',
                'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
            ],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('refreshToken')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->is($token)))
        ->andReturnUsing(function (QuickBooksToken $expiredToken) {
            $expiredToken->update([
                'access_token' => 'refreshed-access',
                'access_token_expires_at' => now()->addHour(),
            ]);

            return $expiredToken->fresh();
        });
    $quickBooks->shouldReceive('dataService')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->id === $token->id))
        ->andReturn($dataService);

    $this->instance(QuickBooksService::class, $quickBooks);

    $this->actingAs($admin)
        ->getJson('/api/quickbooks/employees', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.id', '42')
        ->assertJsonPath('data.0.display_name', 'Jane Doe');
});

it('returns quickbooks expired when employee listing cannot refresh tokens', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->expired()->create(['realm_id' => 'realm-42']);

    $quickBooks = Mockery::mock(QuickBooksService::class)->makePartial();
    $quickBooks->shouldReceive('refreshToken')
        ->once()
        ->andThrow(new QuickBooksException('QuickBooks token refresh failed.'));

    $this->instance(QuickBooksService::class, $quickBooks);

    $this->actingAs($admin)
        ->getJson('/api/quickbooks/employees', frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'quickbooks_expired');
});
