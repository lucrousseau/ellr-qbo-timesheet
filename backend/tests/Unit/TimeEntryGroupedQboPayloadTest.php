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
        ->and($result['payload']['description'])->toContain('Ellr grouped time: 2 entries, 2h00.')
        ->and($result['payload']['description'])->toContain('Details: http://admin.test/?sync_group=abc')
        ->and($result['payload']['description'])->toContain('One')
        ->and($result['payload']['description'])->toContain('Two');
});
