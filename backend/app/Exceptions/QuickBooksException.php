<?php

namespace App\Exceptions;

use Exception;

/**
 * QuickBooks business error (API or tokens) with optional Intuit response body.
 */
class QuickBooksException extends Exception
{
    /**
     * Creates a QuickBooks exception with optional Intuit response body.
     *
     * @param  string  $message  Exception message.
     * @param  string|null  $responseBody  Optional Intuit response body.
     * @param  int  $code  Exception code.
     */
    public function __construct(
        string $message,
        public readonly ?string $responseBody = null,
        int $code = 0,
    ) {
        parent::__construct($message, $code);
    }
}
