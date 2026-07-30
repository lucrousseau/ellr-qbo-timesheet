<?php

/**
 * REST API route definitions for health, auth, QuickBooks OAuth, and time activities.
 */

use App\Http\Controllers\Api\AdminQboEmployeeController;
use App\Http\Controllers\Api\AdminQuickBooksPickerController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AdminUserCustomerController;
use App\Http\Controllers\Api\AdminUserTimeActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\QboEmployeeController;
use App\Http\Controllers\Api\QuickBooksAuthController;
use App\Http\Controllers\Api\QuickBooksEmployeeController;
use App\Http\Controllers\Api\QuickBooksPickerController;
use App\Http\Controllers\Api\QuickBooksWebhookController;
use App\Http\Controllers\Api\TimeActivityController;
use App\Http\Controllers\Api\TimeTrackerController;
use App\Http\Controllers\Api\UserLocaleController;
use App\Http\Controllers\Api\UserPasswordController;
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
    Route::post('/quickbooks/webhook', [QuickBooksWebhookController::class, 'store']);
});

// Sanctum session: logout stays available even when tenant membership is missing.
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Sanctum session required for profile, QBO link, and time activities.
Route::middleware(['auth:sanctum', 'organization', 'throttle:60,1'])->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::patch('/user/locale', [UserLocaleController::class, 'update']);
    Route::patch('/user/password', [UserPasswordController::class, 'update']);
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1');

    Route::middleware('verified.email')->group(function () {
        Route::middleware('admin')->group(function () {
            Route::get('/admin/users', [AdminUserController::class, 'index']);
            Route::post('/admin/users', [AdminUserController::class, 'store']);
            Route::delete('/admin/users/{user}', [AdminUserController::class, 'destroy']);
            Route::get('/admin/users/{user}/customers', [AdminUserCustomerController::class, 'show']);
            Route::put('/admin/users/{user}/customers', [AdminUserCustomerController::class, 'update']);
            Route::get('/admin/users/{user}/time-activities', [AdminUserTimeActivityController::class, 'index']);
            Route::patch('/admin/users/{user}/time-activities/{id}', [AdminUserTimeActivityController::class, 'update']);
            Route::get('/admin/quickbooks/customers', [AdminQuickBooksPickerController::class, 'customers']);
            Route::patch('/user/qbo-employee', [QboEmployeeController::class, 'update']);
            Route::patch('/admin/users/{user}/qbo-employee', [AdminQboEmployeeController::class, 'update']);

            Route::prefix('quickbooks')->group(function () {
                Route::get('/employees', [QuickBooksEmployeeController::class, 'index']);
                Route::get('/status', [QuickBooksAuthController::class, 'status']);
                Route::get('/connect', [QuickBooksAuthController::class, 'connect']);
                Route::post('/disconnect', [QuickBooksAuthController::class, 'disconnect']);
            });
        });

        Route::apiResource('time-activities', TimeActivityController::class);

        Route::get('/quickbooks/customers', [QuickBooksPickerController::class, 'customers']);
        Route::get('/quickbooks/projects', [QuickBooksPickerController::class, 'projects']);
        Route::get('/quickbooks/services', [QuickBooksPickerController::class, 'services']);

        Route::get('/time-tracker', [TimeTrackerController::class, 'show']);
        Route::put('/time-tracker', [TimeTrackerController::class, 'update']);
        Route::post('/time-tracker/log', [TimeTrackerController::class, 'log']);
        Route::delete('/time-tracker', [TimeTrackerController::class, 'destroy']);
    });
});
