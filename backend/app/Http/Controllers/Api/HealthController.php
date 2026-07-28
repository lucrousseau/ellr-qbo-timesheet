<?php

/**
 * Health check endpoint for uptime monitoring.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PasswordPolicy;
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
        $passwordPolicy = PasswordPolicy::config();

        return response()->json([
            'status' => 'ok',
            'service' => 'ellr-qbo-timesheet-api',
            'require_email_verification' => (bool) config('app.require_email_verification'),
            'password_policy' => [
                'loaded' => true,
                'min_length' => $passwordPolicy['minLength'],
            ],
        ]);
    }
}
