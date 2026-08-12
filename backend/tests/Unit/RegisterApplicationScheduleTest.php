<?php

use App\Console\RegisterApplicationSchedule;
use Illuminate\Console\Scheduling\Schedule;

covers(RegisterApplicationSchedule::class);

/**
 * Collects scheduled Artisan command strings from a schedule instance.
 *
 * @param  Schedule  $schedule  Schedule under test.
 * @return list<string>
 */
function scheduledCommands(Schedule $schedule): array
{
    return collect($schedule->events())
        ->map(fn ($event): string => (string) ($event->command ?? ''))
        ->filter()
        ->values()
        ->all();
}

it('registers shared hosting queue drain when enabled', function () {
    config([
        'quickbooks.time_activities_reconcile_enabled' => false,
        'quickbooks.snapshot_soft_delete_retention_days' => 0,
        'queue.failed_jobs_retention_hours' => 0,
        'queue.shared_hosting_drain' => true,
        'queue.shared_hosting_drain_max_time' => 45,
        'queue.shared_hosting_drain_tries' => 2,
    ]);

    $schedule = new Schedule(app());
    (new RegisterApplicationSchedule)($schedule);

    $commands = scheduledCommands($schedule);

    expect($commands)->toHaveCount(1)
        ->and($commands[0])->toContain('queue:work')
        ->and($commands[0])->toContain('--stop-when-empty')
        ->and($commands[0])->toContain('--max-time=45')
        ->and($commands[0])->toContain('--tries=2');
});

it('skips shared hosting queue drain when disabled for cloud workers', function () {
    config([
        'quickbooks.time_activities_reconcile_enabled' => false,
        'quickbooks.snapshot_soft_delete_retention_days' => 0,
        'queue.failed_jobs_retention_hours' => 0,
        'queue.shared_hosting_drain' => false,
    ]);

    $schedule = new Schedule(app());
    (new RegisterApplicationSchedule)($schedule);

    $commands = scheduledCommands($schedule);

    expect($commands)->toBeEmpty();
});

it('still registers reconcile when shared drain is off', function () {
    config([
        'quickbooks.time_activities_reconcile_enabled' => true,
        'quickbooks.time_activities_reconcile_cron' => '0 * * * *',
        'quickbooks.snapshot_soft_delete_retention_days' => 0,
        'queue.failed_jobs_retention_hours' => 0,
        'queue.shared_hosting_drain' => false,
    ]);

    $schedule = new Schedule(app());
    (new RegisterApplicationSchedule)($schedule);

    $commands = scheduledCommands($schedule);

    expect($commands)->toHaveCount(1)
        ->and($commands[0])->toContain('quickbooks:reconcile-time-activities');
});
