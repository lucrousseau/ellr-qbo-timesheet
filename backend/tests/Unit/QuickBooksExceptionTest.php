<?php

use App\Exceptions\QuickBooksException;

covers(QuickBooksException::class);

it('stores an optional quickbooks response body', function () {
    $exception = new QuickBooksException('QuickBooks failed.', 'intuit-error-body');

    expect($exception->getMessage())->toBe('QuickBooks failed.')
        ->and($exception->responseBody)->toBe('intuit-error-body');
});

it('defaults the quickbooks response body to null', function () {
    $exception = new QuickBooksException('QuickBooks failed.');

    expect($exception->responseBody)->toBeNull();
});
