<?php

/**
 * Builds JSON HTTP responses for QuickBooks SDK errors.
 */

namespace App\Services;

use App\Exceptions\QuickBooksException;
use Illuminate\Http\JsonResponse;

/**
 * Maps QuickBooks SDK errors to API JSON payloads.
 */
class QuickBooksApiErrorFormatterService
{
    /**
     * Formats a QuickBooks SDK error as a 422 JSON response.
     *
     * @param  object  $error  QuickBooks SDK error object.
     * @return JsonResponse
     */
    public function jsonResponse(object $error): JsonResponse
    {
        $payload = ['message' => __('api.quickbooks_api_error')];

        if (config('quickbooks.expose_api_errors')) {
            $payload['error'] = $error->getResponseBody();
        }

        return response()->json($payload, 422);
    }

    /**
     * Builds a domain exception for non-HTTP QuickBooks SDK failures.
     *
     * @param  object  $error  QuickBooks SDK error object.
     * @return QuickBooksException
     */
    public function toException(object $error): QuickBooksException
    {
        $body = method_exists($error, 'getResponseBody') ? $error->getResponseBody() : null;

        return new QuickBooksException(
            (string) __('api.quickbooks_api_error'),
            is_string($body) ? $body : null,
            422,
        );
    }

    /**
     * Formats a QuickBooks domain exception as a JSON HTTP response.
     *
     * @param  QuickBooksException  $exception  QuickBooks failure.
     * @return JsonResponse
     */
    public function responseForException(QuickBooksException $exception): JsonResponse
    {
        $payload = ['message' => $exception->getMessage()];

        if (config('quickbooks.expose_api_errors') && $exception->responseBody !== null) {
            $payload['error'] = $exception->responseBody;
        }

        $status = $exception->getCode();

        if ($status < 400 || $status > 599) {
            $status = 422;
        }

        return response()->json($payload, $status);
    }
}
