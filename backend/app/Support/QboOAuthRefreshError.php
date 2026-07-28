<?php

/**
 * Normalizes QuickBooks OAuth refresh failures for application exceptions.
 */

namespace App\Support;

use QuickBooksOnline\API\Exception\SdkException;
use QuickBooksOnline\API\Exception\ServiceException;

/**
 * Extracts Intuit response details from SDK refresh failures.
 */
class QboOAuthRefreshError
{
    /**
     * Returns the Intuit response body when present in the SDK exception message.
     *
     * @param  ServiceException|SdkException  $exception  SDK refresh failure.
     * @return string
     */
    public static function responseBody(ServiceException|SdkException $exception): string
    {
        $message = $exception->getMessage();

        if (preg_match('/Body: \[(.*)\]/s', $message, $matches) === 1) {
            return $matches[1];
        }

        return $message;
    }
}
