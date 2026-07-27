<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Laravel API availability probe.
 */
class HealthController extends Controller
{
    /**
     * Indicates that the API service is responding.
     *
     * @return JsonResponse
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'ellr-qbo-timesheet-api',
        ]);
    }
}
