<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\QuickBooksAuthController;
use App\Http\Controllers\Api\TimeActivityController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'show']);

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::get('/quickbooks/callback', [QuickBooksAuthController::class, 'callback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    Route::prefix('quickbooks')->group(function () {
        Route::get('/connect', [QuickBooksAuthController::class, 'connect']);
        Route::get('/status', [QuickBooksAuthController::class, 'status']);
        Route::post('/disconnect', [QuickBooksAuthController::class, 'disconnect']);
    });

    Route::apiResource('time-activities', TimeActivityController::class);
});
