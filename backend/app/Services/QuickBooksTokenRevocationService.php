<?php

/**
 * Revokes QuickBooks OAuth tokens at Intuit during disconnect flows.
 */

namespace App\Services;

use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use Illuminate\Support\Facades\Http;

/**
 * Calls the Intuit token revocation endpoint for stored refresh tokens.
 */
class QuickBooksTokenRevocationService
{
    /**
     * Revokes a refresh token at Intuit.
     *
     * @param  QuickBooksToken  $token  Stored OAuth token to revoke.
     * @return void
     */
    public function revoke(QuickBooksToken $token): void
    {
        $response = Http::asForm()
            ->withBasicAuth(
                (string) config('quickbooks.client_id'),
                (string) config('quickbooks.client_secret'),
            )
            ->post('https://developer.api.intuit.com/v2/oauth2/tokens/revoke', [
                'token' => $token->refresh_token,
            ]);

        if (! $response->successful()) {
            throw new QuickBooksException('QuickBooks token revocation failed.');
        }
    }
}
