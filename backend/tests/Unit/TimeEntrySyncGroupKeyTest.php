<?php

/**
 * Tests for stable QuickBooks sync grouping keys.
 */

use App\Models\TimeEntry;
use App\Models\User;
use App\Support\TimeEntrySyncGroupKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

covers(TimeEntrySyncGroupKey::class);

uses(RefreshDatabase::class);

it('builds a key from employee day customer project item and billable flag', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'customer_ref' => '10',
        'project_ref' => '20',
        'item_ref' => '33',
        'is_billable' => true,
        'start_time' => Carbon::parse('2026-08-12 22:30:00', 'UTC'),
        'end_time' => Carbon::parse('2026-08-12 23:30:00', 'UTC'),
    ]);

    $result = TimeEntrySyncGroupKey::fromEntry($entry, 'America/Toronto');

    expect($result['txn_date'])->toBe('2026-08-12')
        ->and($result['group_key'])->toBe(implode('|', [
            (string) $employee->organization_id,
            (string) $employee->id,
            '2026-08-12',
            '10',
            '20',
            '33',
            '1',
        ]));
});

it('normalizes null and whitespace refs and uses non-billable zero', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'customer_ref' => null,
        'project_ref' => '  ',
        'item_ref' => ' 44 ',
        'is_billable' => false,
        'start_time' => Carbon::parse('2026-08-12 10:00:00', 'UTC'),
        'end_time' => Carbon::parse('2026-08-12 11:00:00', 'UTC'),
    ]);

    $result = TimeEntrySyncGroupKey::fromEntry($entry, 'UTC');

    expect($result['txn_date'])->toBe('2026-08-12')
        ->and($result['group_key'])->toBe(implode('|', [
            (string) $employee->organization_id,
            (string) $employee->id,
            '2026-08-12',
            '',
            '',
            '44',
            '0',
        ]));
});

it('falls back to now when start time is missing', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00', 'UTC'));

    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'start_time' => Carbon::parse('2026-01-01 08:00:00', 'UTC'),
        'end_time' => Carbon::parse('2026-01-01 09:00:00', 'UTC'),
    ]);
    $entry->start_time = null;

    $result = TimeEntrySyncGroupKey::fromEntry($entry, 'UTC');

    expect($result['txn_date'])->toBe('2026-01-15');

    Carbon::setTestNow();
});
