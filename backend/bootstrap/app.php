<?php

/**
 * Laravel application bootstrap: API routing, stateful Sanctum middleware, and JSON errors for /api.
 */

use App\Exceptions\QuickBooksException;
use App\Http\Middleware\EnsureEmailVerifiedIfRequired;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SetLocaleFromUser;
use App\Services\QuickBooksApiErrorFormatterService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withSchedule(function (Schedule $schedule): void {
        if (config('quickbooks.time_activities_reconcile_enabled', true)) {
            $schedule->command('quickbooks:reconcile-time-activities')
                ->cron((string) config('quickbooks.time_activities_reconcile_cron', '0 * * * *'));
        }
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->appendToGroup('api', SetLocaleFromUser::class);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'verified.email' => EnsureEmailVerifiedIfRequired::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (QuickBooksException $exception, Request $request) {
            return app(QuickBooksApiErrorFormatterService::class)->responseForException($exception);
        });
    })->create();
