<?php

/**
 * Registers Laravel schedule events for QBO reconcile, pruning, and optional shared-hosting queue drain.
 */

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;

/**
 * Central schedule registration so Cloud (Supervisor) and Shared (cron drain) stay config-driven.
 */
final class RegisterApplicationSchedule
{
    /**
     * Registers all application cron tasks on the given schedule.
     *
     * @param  Schedule  $schedule  Laravel schedule instance.
     * @return void
     */
    public function __invoke(Schedule $schedule): void
    {
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

        if (config('queue.shared_hosting_drain', false)) {
            $maxTime = (int) config('queue.shared_hosting_drain_max_time', 50);
            $tries = (int) config('queue.shared_hosting_drain_tries', 3);

            $schedule->command("queue:work --stop-when-empty --max-time={$maxTime} --tries={$tries}")
                ->everyMinute()
                ->withoutOverlapping(5);
        }
    }
}
