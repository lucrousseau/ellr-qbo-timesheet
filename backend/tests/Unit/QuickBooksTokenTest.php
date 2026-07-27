<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(QuickBooksToken::class);

uses(RefreshDatabase::class);

it('detects expired access tokens', function () {
    $token = QuickBooksToken::factory()->expired()->make();

    expect($token->isAccessTokenExpired())->toBeTrue();
});

it('detects valid access tokens', function () {
    $token = QuickBooksToken::factory()->make([
        'access_token_expires_at' => now()->addHour(),
    ]);

    expect($token->isAccessTokenExpired())->toBeFalse();
});

it('treats missing expiry as a valid access token', function () {
    $token = QuickBooksToken::factory()->make([
        'access_token_expires_at' => null,
    ]);

    expect($token->isAccessTokenExpired())->toBeFalse();
});

it('builds an oauth2 access token for the sdk', function () {
    config([
        'quickbooks.client_id' => 'client-id',
        'quickbooks.client_secret' => 'client-secret',
    ]);

    $token = QuickBooksToken::factory()->make([
        'realm_id' => 'realm-1',
        'access_token' => 'access',
        'refresh_token' => 'refresh',
        'access_token_expires_at' => now()->addHour(),
        'refresh_token_expires_at' => now()->addDays(100),
    ]);

    $oauthToken = $token->toOAuth2AccessToken();

    $reflection = new ReflectionClass($oauthToken);
    $accessExpiry = $reflection->getProperty('accessTokenExpiresAt');
    $accessExpiry->setAccessible(true);
    $refreshExpiry = $reflection->getProperty('refreshTokenExpiresAt');
    $refreshExpiry->setAccessible(true);

    expect($oauthToken->getRealmID())->toBe('realm-1')
        ->and($oauthToken->getAccessToken())->toBe('access')
        ->and($oauthToken->getRefreshToken())->toBe('refresh')
        ->and($accessExpiry->getValue($oauthToken))->toBe($token->access_token_expires_at->toIso8601String())
        ->and($refreshExpiry->getValue($oauthToken))->toBe($token->refresh_token_expires_at->toIso8601String());
});

it('declares mass assignable attributes', function () {
    expect((new QuickBooksToken)->getFillable())->toBe([
        'user_id',
        'realm_id',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'refresh_token_expires_at',
    ]);
});

it('hides sensitive token fields from serialization', function () {
    expect((new QuickBooksToken)->getHidden())->toBe([
        'access_token',
        'refresh_token',
    ]);
});

it('casts sensitive and datetime fields', function () {
    $casts = (new QuickBooksToken)->getCasts();

    expect($casts['access_token'])->toBe('encrypted')
        ->and($casts['refresh_token'])->toBe('encrypted')
        ->and($casts['access_token_expires_at'])->toBe('datetime')
        ->and($casts['refresh_token_expires_at'])->toBe('datetime');
});

it('encrypts quickbooks tokens at rest', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create([
        'access_token' => 'plain-access',
        'refresh_token' => 'plain-refresh',
    ]);

    expect($token->getRawOriginal('access_token'))->not->toBe('plain-access')
        ->and($token->access_token)->toBe('plain-access')
        ->and($token->getRawOriginal('refresh_token'))->not->toBe('plain-refresh')
        ->and($token->refresh_token)->toBe('plain-refresh');
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create();

    expect($token->user->is($user))->toBeTrue();
});
