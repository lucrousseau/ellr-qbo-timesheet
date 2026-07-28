<?php

/**
 * Builds JSON HTTP responses for QuickBooks SDK errors.
 */

namespace App\Services;

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
}
