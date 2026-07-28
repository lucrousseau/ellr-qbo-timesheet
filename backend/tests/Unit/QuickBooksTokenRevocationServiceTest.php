<?php

use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Services\QuickBooksTokenRevocationService;
use Illuminate\Support\Facades\Http;

covers(QuickBooksTokenRevocationService::class);

it('revokes a refresh token at intuit', function () {
    config([
        'quickbooks.client_id' => 12345,
        'quickbooks.client_secret' => 67890,
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
            && $request['token'] === 'refresh-token-value'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('12345:67890'));
    });
});

it('casts non-string quickbooks oauth credentials before revocation', function () {
    config([
        'quickbooks.client_id' => new class
        {
            public function __toString(): string
            {
                return 'string-client-id';
            }
        },
        'quickbooks.client_secret' => new class
        {
            public function __toString(): string
            {
                return 'string-client-secret';
            }
        },
    ]);

    Http::fake([
        'developer.api.intuit.com/*' => Http::response('', 200),
    ]);

    $token = QuickBooksToken::factory()->make([
        'refresh_token' => 'refresh-token-value',
    ]);

    app(QuickBooksTokenRevocationService::class)->revoke($token);

    Http::assertSent(function ($request) {
        return $request->hasHeader(
            'Authorization',
            'Basic '.base64_encode('string-client-id:string-client-secret'),
        );
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
