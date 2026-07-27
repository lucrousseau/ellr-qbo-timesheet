<?php

/**
 * REST API route definitions for health, auth, QuickBooks OAuth, and time activities.
 */

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\QboEmployeeController;
use App\Http\Controllers\Api\QuickBooksAuthController;
use App\Http\Controllers\Api\TimeActivityController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'show']);

// Public auth (registration gated by ALLOW_REGISTRATION in AuthController).
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Intuit OAuth redirect (no session required; validated in QuickBooksAuthController).
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/quickbooks/callback', [QuickBooksAuthController::class, 'callback']);
});

// Sanctum session required for profile, QBO link, and time activities.
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::patch('/user/qbo-employee', [QboEmployeeController::class, 'update']);

    // QuickBooks tenant connection (OAuth URL, status, disconnect).
    Route::prefix('quickbooks')->group(function () {
        Route::get('/connect', [QuickBooksAuthController::class, 'connect']);
        Route::get('/status', [QuickBooksAuthController::class, 'status']);
        Route::post('/disconnect', [QuickBooksAuthController::class, 'disconnect']);
    });

    Route::apiResource('time-activities', TimeActivityController::class);
});
