<?php

use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Services\QuickBooksTokenRevocationService;
use Illuminate\Support\Facades\Http;

covers(QuickBooksTokenRevocationService::class);

it('revokes a refresh token at intuit', function () {
    config([
        'quickbooks.client_id' => 'client-id',
        'quickbooks.client_secret' => 'client-secret',
    ]);

    Http::fake([
        'developer.api.intuit.com/*' => Http::response('', 200),
    ]);

    $token = QuickBooksToken::factory()->make([
        'refresh_token' => 'refresh-token-value',
    ]);

    app(QuickBooksTokenRevocationService::class)->revoke($token);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke'
            && $request['token'] === 'refresh-token-value';
    });
});

it('throws when intuit token revocation fails', function () {
    config([
        'quickbooks.client_id' => 'client-id',
        'quickbooks.client_secret' => 'client-secret',
    ]);

    Http::fake([
        'developer.api.intuit.com/*' => Http::response('error', 400),
    ]);

    $token = QuickBooksToken::factory()->make([
        'refresh_token' => 'refresh-token-value',
    ]);

    expect(fn () => app(QuickBooksTokenRevocationService::class)->revoke($token))
        ->toThrow(QuickBooksException::class, 'QuickBooks token revocation failed.');
});
