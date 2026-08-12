<?php

/**
 * Laravel application bootstrap: API routing, stateful Sanctum middleware, and JSON errors for /api.
 */

use App\Exceptions\QuickBooksException;
use App\Http\Middleware\EnsureEmailVerifiedIfRequired;
use App\Http\Middleware\EnsureUserHasOrganization;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
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
            $schedule->command('quickbooks:reconcile-time-activities --scheduled')
                ->cron((string) config('quickbooks.time_activities_reconcile_cron', '0 * * * *'));
        }

        if ((int) config('quickbooks.snapshot_soft_delete_retention_days', 90) > 0) {
            $schedule->command('quickbooks:prune-time-activity-snapshots')->daily();
        }

        $failedJobRetentionHours = (int) config('queue.failed_jobs_retention_hours', 168);

        if ($failedJobRetentionHours > 0) {
            $schedule->command('queue:prune-failed', [
                '--hours' => $failedJobRetentionHours,
            ])->daily();
        }

        // SiteGround Shared: drain the queue from cron. Cloud/VPS: leave false and use Supervisor.
        if (config('queue.shared_hosting_drain')) {
            $maxTime = (int) config('queue.shared_hosting_drain_max_time');
            $tries = (int) config('queue.shared_hosting_drain_tries');

            $schedule->command("queue:work --stop-when-empty --max-time={$maxTime} --tries={$tries}")
                ->everyMinute()
                ->withoutOverlapping(5);
        }
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->appendToGroup('api', SetLocaleFromUser::class);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'organization' => EnsureUserHasOrganization::class,
            'super_admin' => EnsureUserIsSuperAdmin::class,
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
