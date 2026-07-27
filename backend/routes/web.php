<?php

/**
 * Root URL JSON response pointing API clients to health and the React frontends.
 */

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'ellr-qbo-timesheet-api',
        'health' => url('/api/health'),
        'message' => 'API only. Use the React admin and timesheet apps for the UI.',
    ]);
});
