<?php

use App\Support\QboOAuthRefreshError;
use QuickBooksOnline\API\Exception\SdkException;
use QuickBooksOnline\API\Exception\ServiceException;

covers(QboOAuthRefreshError::class);

it('extracts the intuit response body from a service exception message', function () {
    $exception = new ServiceException(
        'Refresh OAuth 2 Access token with Refresh Token failed. Body: [invalid_grant].',
        400,
    );

    expect(QboOAuthRefreshError::responseBody($exception))->toBe('invalid_grant');
});

it('falls back to the sdk exception message when no body is present', function () {
    $exception = new SdkException('SDK refresh failed.');

    expect(QboOAuthRefreshError::responseBody($exception))->toBe('SDK refresh failed.');
});
