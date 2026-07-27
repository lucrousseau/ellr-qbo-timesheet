<?php

namespace App\Exceptions;

use Exception;

class QuickBooksException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $responseBody = null,
        int $code = 0,
    ) {
        parent::__construct($message, $code);
    }
}
