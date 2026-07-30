<?php

use App\Models\TimeActivitySnapshot;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\TimeActivityDuration;
use App\Support\TimeEntryApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

covers(TimeActivityDuration::class);
covers(TimeEntryApiResponse::class);

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

it('maps a local time entry resource for api responses', function () {
    $employee = User::factory()->create(['name' => 'Jane Doe']);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'description' => 'Pending local',
    ]);

    $payload = TimeEntryApiResponse::resource($entry);

    expect($payload['list_id'])->toBe('local:'.$entry->id)
        ->and($payload['status'])->toBe('pending')
        ->and($payload['employee_name'])->toBe('Jane Doe')
        ->and($payload['duration_seconds'])->toBeGreaterThan(0);
});

it('returns qbo duration for multi-day entries using absolute elapsed time', function () {
    $start = Carbon::parse('2026-07-28 09:00:00', 'UTC');
    $end = Carbon::parse('2026-07-30 17:00:00', 'UTC');

    expect(TimeActivityDuration::qboDurationSeconds($start, $end, 'America/Los_Angeles'))->toBe(201_600);
});

it('returns zero seconds when either timestamp is missing', function () {
    $start = Carbon::parse('2026-07-30 09:00:00');

    expect(TimeActivityDuration::secondsBetween(null, $start))->toBe(0)
        ->and(TimeActivityDuration::secondsBetween($start, null))->toBe(0)
        ->and(TimeActivityDuration::qboDurationSeconds(null, $start, 'UTC'))->toBe(0);
});

it('keeps ordered ranges unchanged during normalization', function () {
    $start = Carbon::parse('2026-07-30 09:00:00');
    $end = Carbon::parse('2026-07-30 17:00:00');

    [$normalizedStart, $normalizedEnd] = TimeActivityDuration::normalizeRange($start, $end);

    expect($normalizedStart?->equalTo($start))->toBeTrue()
        ->and($normalizedEnd?->equalTo($end))->toBeTrue();
});

it('returns null pairs unchanged during normalization', function () {
    expect(TimeActivityDuration::normalizeRange(null, null))->toBe([null, null]);
});

it('maps a collection of local entries for api responses', function () {
    $employee = User::factory()->create(['name' => 'Jane Doe']);
    $reviewer = User::factory()->admin()->create(['name' => 'Admin User']);
    $entries = TimeEntry::factory()->count(2)->forUser($employee)->create();
    $entries[0]->update([
        'reviewed_by_id' => $reviewer->id,
        'reviewed_at' => now(),
    ]);

    $payloads = TimeEntryApiResponse::collection($entries->fresh(['user', 'reviewedBy']));

    expect($payloads)->toHaveCount(2)
        ->and($payloads[0]['employee_name'])->toBe('Jane Doe')
        ->and($payloads[0]['reviewed_by_name'])->toBe('Admin User');
});

it('builds customer labels from snapshot customer and project names', function () {
    $withBoth = TimeEntryApiResponse::fromSnapshot(TimeActivitySnapshot::factory()->make([
        'customer_name' => 'Acme Corp',
        'project_name' => 'Website',
    ]));
    $customerOnly = TimeEntryApiResponse::fromSnapshot(TimeActivitySnapshot::factory()->make([
        'customer_name' => 'Acme Corp',
        'project_name' => null,
    ]));
    $sameName = TimeEntryApiResponse::fromSnapshot(TimeActivitySnapshot::factory()->make([
        'customer_name' => 'Acme Corp',
        'project_name' => 'Acme Corp',
    ]));

    expect($withBoth['customer_name'])->toBe('Acme Corp:Website')
        ->and($customerOnly['customer_name'])->toBe('Acme Corp')
        ->and($sameName['customer_name'])->toBe('Acme Corp');
});

it('marks billable quickbooks snapshots in unified list payloads', function () {
    $snapshot = TimeActivitySnapshot::factory()->make([
        'billable_status' => 'Billable',
        'qbo_id' => '55',
    ]);

    $payload = TimeEntryApiResponse::fromSnapshot($snapshot);

    expect($payload['is_billable'])->toBeTrue()
        ->and($payload['status'])->toBe('approved')
        ->and($payload['list_id'])->toBe('qbo:55');
});
