<?php

/**
 * Tests for aggregated QuickBooks description and duration payloads.
 */

use App\Models\TimeEntry;
use App\Models\User;
use App\Support\TimeEntryGroupedQboPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

covers(TimeEntryGroupedQboPayload::class);

uses(RefreshDatabase::class);

it('aggregates duration and embeds the detail url in the description', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $day = now()->startOfDay()->addHours(9);
    $entries = Collection::make([
        TimeEntry::factory()->forUser($employee)->create([
            'item_ref' => '33',
            'description' => 'One',
            'is_billable' => true,
            'start_time' => $day->copy(),
            'end_time' => $day->copy()->addHour(),
        ]),
        TimeEntry::factory()->forUser($employee)->create([
            'item_ref' => '33',
            'description' => 'Two',
            'is_billable' => true,
            'start_time' => $day->copy()->addHours(2),
            'end_time' => $day->copy()->addHours(3),
        ]),
    ]);

    $result = TimeEntryGroupedQboPayload::fromEntries(
        $entries,
        'http://admin.test/?sync_group=abc',
        'UTC',
        ['item_name' => 'Programming'],
    );

    expect($result['member_count'])->toBe(2)
        ->and($result['total_duration_seconds'])->toBe(7200)
        ->and($result['payload']['is_billable'])->toBeTrue()
        ->and($result['payload']['item_ref'])->toBe('33')
        ->and($result['payload']['item_name'])->toBe('Programming')
        ->and($result['payload']['start_time'])->toBe($day->copy()->toIso8601String())
        ->and($result['payload']['end_time'])->toBe($day->copy()->addSeconds(7200)->toIso8601String())
        ->and($result['payload']['description'])->toBe(implode("\n", [
            'Ellr grouped time: 2 entries, 2h00.',
            'Details: http://admin.test/?sync_group=abc',
            '- 09:00-10:00 One',
            '- 11:00-12:00 Two',
        ]));
});

it('uses singular entry wording and omits blank notes', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $day = now()->startOfDay()->addHours(14);
    $entries = Collection::make([
        TimeEntry::factory()->forUser($employee)->create([
            'description' => '   ',
            'is_billable' => false,
            'start_time' => $day->copy(),
            'end_time' => $day->copy()->addMinutes(90),
        ]),
    ]);

    $result = TimeEntryGroupedQboPayload::fromEntries(
        $entries,
        'http://admin.test/?sync_group=solo',
        'UTC',
    );

    expect($result['member_count'])->toBe(1)
        ->and($result['total_duration_seconds'])->toBe(5400)
        ->and($result['payload']['is_billable'])->toBeFalse()
        ->and($result['payload']['description'])->toBe(implode("\n", [
            'Ellr grouped time: 1 entry, 1h30.',
            'Details: http://admin.test/?sync_group=solo',
            '- 14:00-15:30',
        ]));
});

it('renders missing clocks as placeholders', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'description' => 'No clock',
        'start_time' => now()->startOfDay()->addHours(9),
        'end_time' => now()->startOfDay()->addHours(10),
    ]);
    $entry->start_time = null;
    $entry->end_time = null;

    $description = TimeEntryGroupedQboPayload::description(
        Collection::make([$entry]),
        'http://admin.test/?sync_group=missing',
        'UTC',
        3600,
    );

    expect($description)->toContain('- --:-----:-- No clock');
});

it('truncates long quickbooks descriptions', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $day = now()->startOfDay()->addHours(8);
    $entries = Collection::make();

    for ($index = 0; $index < 80; $index++) {
        $start = $day->copy()->addMinutes($index);
        $entries->push(TimeEntry::factory()->forUser($employee)->create([
            'description' => str_repeat('x', 80),
            'start_time' => $start,
            'end_time' => $start->copy()->addMinute(),
        ]));
    }

    $description = TimeEntryGroupedQboPayload::description(
        $entries,
        'http://admin.test/?sync_group=long',
        'UTC',
        4800,
    );

    expect(strlen($description))->toBe(3900)
        ->and(str_ends_with($description, '...'))->toBeTrue();
});
