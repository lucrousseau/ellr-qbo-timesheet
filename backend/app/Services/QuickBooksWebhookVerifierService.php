<?php

/**
 * Verifies Intuit QuickBooks webhook request signatures.
 */

namespace App\Services;

/**
 * Validates the intuit-signature header against the raw webhook payload.
 */
class QuickBooksWebhookVerifierService
{
    /**
     * Returns whether the webhook signature matches the configured verifier token.
     *
     * @param  string  $payload  Raw request body.
     * @param  string|null  $signature  Value of the intuit-signature header.
     * @return bool
     */
    public function isValid(string $payload, ?string $signature): bool
    {
        $verifier = (string) config('quickbooks.webhook_verifier', '');

        if ($verifier === '' || $signature === null || $signature === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $payload, $verifier, true));

        return hash_equals($expected, $signature);
    }
}
