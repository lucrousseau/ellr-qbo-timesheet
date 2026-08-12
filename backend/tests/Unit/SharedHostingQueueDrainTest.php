<?php

use App\Console\SharedHostingQueueDrain;
use Illuminate\Console\Scheduling\Schedule;

covers(SharedHostingQueueDrain::class);

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
        'queue.shared_hosting_drain' => true,
        'queue.shared_hosting_drain_max_time' => 45,
        'queue.shared_hosting_drain_tries' => 2,
    ]);

    $schedule = new Schedule(app());
    (new SharedHostingQueueDrain)->register($schedule);

    $commands = scheduledCommands($schedule);
    $drainEvent = $schedule->events()[0];

    expect($commands)->toHaveCount(1)
        ->and($commands[0])->toContain('queue:work')
        ->and($commands[0])->toContain('--stop-when-empty')
        ->and($commands[0])->toContain('--max-time=45')
        ->and($commands[0])->toContain('--tries=2')
        ->and($drainEvent->withoutOverlapping)->toBeTrue()
        ->and($drainEvent->expiresAt)->toBe(5);
});

it('uses queue config values for shared hosting drain timing', function () {
    config([
        'queue.shared_hosting_drain' => true,
        'queue.shared_hosting_drain_max_time' => 50,
        'queue.shared_hosting_drain_tries' => 3,
    ]);

    $schedule = new Schedule(app());
    (new SharedHostingQueueDrain)->register($schedule);

    $commands = scheduledCommands($schedule);

    expect($commands)->toHaveCount(1)
        ->and($commands[0])->toContain('--max-time=50')
        ->and($commands[0])->toContain('--tries=3');
});

it('skips shared hosting queue drain when disabled for cloud workers', function () {
    config(['queue.shared_hosting_drain' => false]);

    $schedule = new Schedule(app());
    (new SharedHostingQueueDrain)->register($schedule);

    expect(scheduledCommands($schedule))->toBeEmpty();
});
