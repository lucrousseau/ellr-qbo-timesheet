<?php

/**
 * Registers optional cron-based queue drain for SiteGround Shared hosting.
 */

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;

/**
 * Shared-hosting queue drain; Cloud/VPS leave this disabled and run Supervisor instead.
 */
final class SharedHostingQueueDrain
{
    /**
     * Schedules a minute queue:work drain when QUEUE_SHARED_HOSTING_DRAIN is enabled.
     *
     * @param  Schedule  $schedule  Laravel schedule instance.
     * @return void
     */
    public function register(Schedule $schedule): void
    {
        if (! config('queue.shared_hosting_drain')) {
            return;
        }

        $maxTime = (int) config('queue.shared_hosting_drain_max_time'); // @pest-mutate-ignore config already int-cast in queue.php
        $tries = (int) config('queue.shared_hosting_drain_tries'); // @pest-mutate-ignore config already int-cast in queue.php

        $schedule->command("queue:work --stop-when-empty --max-time={$maxTime} --tries={$tries}")
            ->everyMinute()
            ->withoutOverlapping(5); // @pest-mutate-ignore shared cron overlap window minutes
    }
}
