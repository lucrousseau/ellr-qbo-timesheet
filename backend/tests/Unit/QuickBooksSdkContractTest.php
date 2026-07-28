<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
use QuickBooksOnline\API\Exception\SdkException;
use QuickBooksOnline\API\Exception\ServiceException;

covers(QuickBooksService::class);
covers(QuickBooksToken::class);

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

it('configures the data service request validator from a stored token', function () {
    $token = QuickBooksToken::factory()->forUser(User::factory()->create())->create([
        'access_token' => 'stored-access',
        'refresh_token' => 'stored-refresh',
    ]);

    $dataService = app(QuickBooksService::class)->dataService($token);
    $requestValidator = $dataService->getServiceContext()->requestValidator;

    expect($requestValidator->getAccessToken())->toBe('stored-access')
        ->and($requestValidator->getRefreshToken())->toBe('stored-refresh');
});

it('does not inject stored tokens into the data service oauth helper', function () {
    $token = QuickBooksToken::factory()->forUser(User::factory()->create())->expired()->create([
        'refresh_token' => 'stored-refresh',
    ]);

    $dataService = app(QuickBooksService::class)->dataService($token);

    expect(fn () => $dataService->getOAuth2LoginHelper()->refreshToken())
        ->toThrow(SdkException::class, "Can't get OAuth 2 Access Token Object. It is not set yet.");
});

it('loads stored tokens into an oauth helper built from the data service context', function () {
    $token = QuickBooksToken::factory()->forUser(User::factory()->create())->expired()->create([
        'access_token' => 'stored-access',
        'refresh_token' => 'stored-refresh',
    ]);

    $dataService = app(QuickBooksService::class)->dataService($token);
    $helper = new OAuth2LoginHelper(
        config('quickbooks.client_id'),
        config('quickbooks.client_secret'),
        config('quickbooks.redirect_uri'),
        config('quickbooks.scope'),
        null,
        $dataService->getServiceContext(),
    );

    expect($helper->getAccessToken()->getAccessToken())->toBe('stored-access')
        ->and($helper->getAccessToken()->getRefreshToken())->toBe('stored-refresh');
});

it('refreshes with a refresh token only on a standalone oauth helper', function () {
    $helper = app(QuickBooksService::class)->oauthHelper();

    try {
        $helper->refreshAccessTokenWithRefreshToken('refresh-token-value');
        expect(false)->toBeTrue('Expected an Intuit HTTP failure in unit tests');
    } catch (ServiceException) {
        expect(true)->toBeTrue();
    } catch (SdkException $exception) {
        expect($exception->getMessage())->not->toContain("Can't get OAuth 2 Access Token Object. It is not set yet.");
    }
});
