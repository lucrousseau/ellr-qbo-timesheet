<?php

/**
 * Health check endpoint for uptime monitoring.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Reports API availability for load balancers and uptime monitors.
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
            'require_email_verification' => (bool) config('app.require_email_verification'),
            'time_tracker_max_accumulated_seconds' => (int) config(
                'quickbooks.time_tracker_max_accumulated_seconds',
            ),
        ]);
    }
}
