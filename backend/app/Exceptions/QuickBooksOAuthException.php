<?php

/**
 * Domain exception for QuickBooks OAuth flow failures.
 */

namespace App\Exceptions;

/**
 * Thrown when OAuth state, redirect, or token exchange fails.
 */
class QuickBooksOAuthException extends QuickBooksException
{
    /**
     * Creates an OAuth exception with an optional frontend redirect reason code.
     *
     * @param  string  $message  Exception message.
     * @param  string|null  $responseBody  Optional Intuit response body.
     * @param  string|null  $reason  Optional admin OAuth callback reason code.
     */
    public function __construct(
        string $message,
        ?string $responseBody = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($message, $responseBody);
    }
}
