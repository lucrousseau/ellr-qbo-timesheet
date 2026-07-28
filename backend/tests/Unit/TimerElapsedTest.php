<?php

use App\Models\ActiveTimeSession;
use App\Support\TimerElapsed;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

covers(TimerElapsed::class);

it('computes elapsed seconds for a running session', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:30');

    $session = new ActiveTimeSession([
        'accumulated_seconds' => 120,
        'running_since' => CarbonImmutable::parse('2026-07-28 12:00:00'),
    ]);

    expect(TimerElapsed::forSession($session))->toBe(150);

    CarbonImmutable::setTestNow();
});

it('caps elapsed seconds at the configured maximum', function () {
    config(['quickbooks.time_tracker_max_accumulated_seconds' => 60]);

    $session = new ActiveTimeSession([
        'accumulated_seconds' => 120,
        'running_since' => null,
    ]);

    expect(TimerElapsed::forSession($session))->toBe(60);
});

it('parses string running_since values', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:01:00');

    $session = new ActiveTimeSession([
        'accumulated_seconds' => 0,
        'running_since' => '2026-07-28 12:00:00',
    ]);

    expect(TimerElapsed::forSession($session))->toBe(60);

    CarbonImmutable::setTestNow();
});

it('returns paused elapsed time without a running segment', function () {
    $session = new ActiveTimeSession([
        'accumulated_seconds' => 45,
        'running_since' => null,
    ]);

    expect(TimerElapsed::forSession($session))->toBe(45);
});

it('returns null for unsupported running_since values', function () {
    expect(TimerElapsed::asCarbon(null))->toBeNull()
        ->and(TimerElapsed::asCarbon(''))->toBeNull()
        ->and(TimerElapsed::asCarbon(123))->toBeNull();
});

it('uses configured max seconds with a minimum of one', function () {
    config(['quickbooks.time_tracker_max_accumulated_seconds' => 0]);

    expect(TimerElapsed::maxSeconds())->toBe(1);
});

it('returns the configured maximum elapsed seconds', function () {
    config(['quickbooks.time_tracker_max_accumulated_seconds' => 3600]);

    expect(TimerElapsed::maxSeconds())->toBe(3600);
});

it('ignores negative running segment deltas', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    $session = new ActiveTimeSession([
        'accumulated_seconds' => 10,
        'running_since' => CarbonImmutable::parse('2026-07-28 12:05:00'),
    ]);

    expect(TimerElapsed::forSession($session))->toBe(10);

    CarbonImmutable::setTestNow();
});

it('returns the same carbon instance when already parsed', function () {
    $value = Carbon::parse('2026-07-28 12:00:00');

    expect(TimerElapsed::asCarbon($value))->toBe($value);
});
