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

it('builds quickbooks exceptions from sdk errors', function () {
    $error = new class
    {
        public function getResponseBody(): string
        {
            return 'raw-body';
        }
    };

    $exception = app(QuickBooksApiErrorFormatterService::class)->toException($error);

    expect($exception)->toBeInstanceOf(QuickBooksException::class)
        ->and($exception->responseBody)->toBe('raw-body');
});

it('hides quickbooks exception bodies when exposure is disabled', function () {
    config(['quickbooks.expose_api_errors' => false]);

    $response = app(QuickBooksApiErrorFormatterService::class)->responseForException(
        new QuickBooksException('QuickBooks API error', 'raw-body', 422),
    );

    expect($response->getData(true))->toBe(['message' => 'QuickBooks API error']);
});

it('falls back to 422 for invalid quickbooks exception status codes', function () {
    config(['quickbooks.expose_api_errors' => true]);

    $response = app(QuickBooksApiErrorFormatterService::class)->responseForException(
        new QuickBooksException('QuickBooks API error', 'raw-body', 200),
    );

    expect($response->getStatusCode())->toBe(422);
});

it('builds quickbooks exceptions when sdk errors omit response bodies', function () {
    $exception = app(QuickBooksApiErrorFormatterService::class)->toException(new stdClass);

    expect($exception->responseBody)->toBeNull();
});

it('preserves valid quickbooks exception status codes', function () {
    config(['quickbooks.expose_api_errors' => false]);

    $response = app(QuickBooksApiErrorFormatterService::class)->responseForException(
        new QuickBooksException('QuickBooks API error', 'raw-body', 503),
    );

    expect($response->getStatusCode())->toBe(503)
        ->and($response->getData(true))->toBe(['message' => 'QuickBooks API error']);
});

it('clamps quickbooks exception status codes above 599', function () {
    $response = app(QuickBooksApiErrorFormatterService::class)->responseForException(
        new QuickBooksException('QuickBooks API error', null, 700),
    );

    expect($response->getStatusCode())->toBe(422);
});

it('clamps quickbooks exception status codes below 400', function () {
    $response = app(QuickBooksApiErrorFormatterService::class)->responseForException(
        new QuickBooksException('QuickBooks API error', null, 399),
    );

    expect($response->getStatusCode())->toBe(422);
});

it('builds quickbooks exceptions when response bodies are not strings', function () {
    $error = new class
    {
        public function getResponseBody(): array
        {
            return ['detail' => 'failed'];
        }
    };

    $exception = app(QuickBooksApiErrorFormatterService::class)->toException($error);

    expect($exception->responseBody)->toBeNull();
});
