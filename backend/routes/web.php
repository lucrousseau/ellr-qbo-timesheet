<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'ellr-qbo-timesheet-api',
        'health' => url('/api/health'),
        'message' => 'API only. Use the React admin and timesheet apps for the UI.',
    ]);
});
