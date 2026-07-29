<?php

use App\Exceptions\QuickBooksException;
use App\Services\QuickBooksApiErrorFormatterService;

covers(QuickBooksApiErrorFormatterService::class);

it('returns a generic quickbooks api error payload', function () {
    config(['quickbooks.expose_api_errors' => false]);

    $error = new class
    {
        public function getResponseBody(): string
        {
            return 'raw-body';
        }
    };

    $response = app(QuickBooksApiErrorFormatterService::class)->jsonResponse($error);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe(['message' => 'QuickBooks API error']);
});

it('exposes quickbooks error bodies when configured', function () {
    config(['quickbooks.expose_api_errors' => true]);

    $error = new class
    {
        public function getResponseBody(): string
        {
            return 'raw-body';
        }
    };

    $response = app(QuickBooksApiErrorFormatterService::class)->jsonResponse($error);

    expect($response->getData(true))->toBe([
        'message' => 'QuickBooks API error',
        'error' => 'raw-body',
    ]);
});

it('formats quickbooks domain exceptions when configured', function () {
    config(['quickbooks.expose_api_errors' => true]);

    $response = app(QuickBooksApiErrorFormatterService::class)->responseForException(
        new QuickBooksException('QuickBooks API error', 'raw-body', 422),
    );

    expect($response->getData(true))->toBe([
        'message' => 'QuickBooks API error',
        'error' => 'raw-body',
    ]);
});
