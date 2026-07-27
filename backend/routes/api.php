<?php

/**
 * REST API route definitions for health, auth, QuickBooks OAuth, and time activities.
 */

use App\Http\Controllers\Api\AdminQboEmployeeController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\QboEmployeeController;
use App\Http\Controllers\Api\QuickBooksAuthController;
use App\Http\Controllers\Api\TimeActivityController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'show']);

// Public auth (registration gated by ALLOW_REGISTRATION in AuthController).
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'forgot']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
});

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// Intuit OAuth redirect (no session required; validated in QuickBooksAuthController).
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/quickbooks/callback', [QuickBooksAuthController::class, 'callback']);
});

// Sanctum session required for profile, QBO link, and time activities.
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1');

    Route::middleware('verified.email')->group(function () {
        Route::middleware('admin')->group(function () {
            Route::patch('/user/qbo-employee', [QboEmployeeController::class, 'update']);
            Route::patch('/admin/users/{user}/qbo-employee', [AdminQboEmployeeController::class, 'update']);

            Route::prefix('quickbooks')->group(function () {
                Route::get('/status', [QuickBooksAuthController::class, 'status']);
                Route::get('/connect', [QuickBooksAuthController::class, 'connect']);
                Route::post('/disconnect', [QuickBooksAuthController::class, 'disconnect']);
            });
        });

        Route::apiResource('time-activities', TimeActivityController::class);
    });
});
