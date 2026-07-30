<?php

use App\Models\TimeActivitySnapshot;
use App\Support\TimeActivityDuration;
use App\Support\TimeEntryApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

covers(TimeActivityDuration::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['quickbooks.company_timezone' => 'America/Los_Angeles']);
});

it('returns elapsed seconds when end is after start', function () {
    $start = Carbon::parse('2026-07-30 15:29:17');
    $end = Carbon::parse('2026-07-30 17:28:24');

    expect(TimeActivityDuration::secondsBetween($start, $end))->toBe(7147);
});

it('returns qbo duration when end clock is before start on the same day', function () {
    $start = Carbon::parse('2026-07-31 06:09:22', 'UTC');
    $end = Carbon::parse('2026-07-30 17:20:22', 'UTC');

    expect(TimeActivityDuration::qboDurationSeconds($start, $end, 'America/Los_Angeles'))->toBe(40_260);
});

it('returns qbo duration for same-day entries with normal clock order', function () {
    $start = Carbon::parse('2026-07-30 15:29:17', 'UTC');
    $end = Carbon::parse('2026-07-30 17:28:24', 'UTC');

    expect(TimeActivityDuration::qboDurationSeconds($start, $end, 'America/Los_Angeles'))->toBe(7147);
});

it('normalizes inverted ranges for local entries', function () {
    $start = Carbon::parse('2026-07-31 06:09:22');
    $end = Carbon::parse('2026-07-30 17:20:22');

    [$normalizedStart, $normalizedEnd] = TimeActivityDuration::normalizeRange($start, $end);

    expect($normalizedStart?->toDateTimeString())->toBe('2026-07-30 17:20:22')
        ->and($normalizedEnd?->toDateTimeString())->toBe('2026-07-31 06:09:22');
});

it('maps snapshot duration using qbo clock-time rules', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'qbo_id' => '1073741840',
        'start_time' => Carbon::parse('2026-07-30 15:29:17', 'UTC'),
        'end_time' => Carbon::parse('2026-07-30 17:28:24', 'UTC'),
        'customer_name' => 'Bill\'s Windsurf Shop:Test ABC',
        'project_name' => null,
        'item_name' => 'Hours',
    ]);

    $payload = TimeEntryApiResponse::fromSnapshot($snapshot);

    expect($payload['duration_seconds'])->toBe(7147)
        ->and($payload['customer_name'])->toBe('Bill\'s Windsurf Shop:Test ABC')
        ->and($payload['start_time'])->toBe('2026-07-30T15:29:17+00:00')
        ->and($payload['end_time'])->toBe('2026-07-30T17:28:24+00:00');
});

it('maps inverted qbo snapshot durations with midnight wraparound', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'qbo_id' => '1073741837',
        'start_time' => Carbon::parse('2026-07-31 06:09:22', 'UTC'),
        'end_time' => Carbon::parse('2026-07-30 17:20:22', 'UTC'),
        'customer_name' => null,
        'item_name' => 'Hours',
    ]);

    $payload = TimeEntryApiResponse::fromSnapshot($snapshot);

    expect($payload['duration_seconds'])->toBe(40_260);
});

it('returns qbo duration for multi-day entries using absolute elapsed time', function () {
    $start = Carbon::parse('2026-07-28 09:00:00', 'UTC');
    $end = Carbon::parse('2026-07-30 17:00:00', 'UTC');

    expect(TimeActivityDuration::qboDurationSeconds($start, $end, 'America/Los_Angeles'))->toBe(201_600);
});
