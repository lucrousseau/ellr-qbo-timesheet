<?php

/**
 * Tests for TimeEntrySyncGroup Eloquent relations and casts.
 */

use App\Models\TimeEntry;
use App\Models\TimeEntrySyncGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

covers(TimeEntrySyncGroup::class);

uses(RefreshDatabase::class);

it('casts attributes and exposes user organization and entry relations', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $group = TimeEntrySyncGroup::factory()->forUser($employee)->create([
        'txn_date' => '2026-08-12',
        'is_billable' => 1,
        'member_count' => '2',
        'total_duration_seconds' => '7200',
        'synced_at' => '2026-08-12 15:00:00',
    ]);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'sync_group_id' => $group->id,
    ]);

    $group->refresh();

    expect($group->txn_date)->toBeInstanceOf(Carbon::class)
        ->and($group->txn_date->toDateString())->toBe('2026-08-12')
        ->and($group->is_billable)->toBeTrue()
        ->and($group->member_count)->toBe(2)
        ->and($group->total_duration_seconds)->toBe(7200)
        ->and($group->synced_at)->toBeInstanceOf(Carbon::class)
        ->and($group->user->is($employee))->toBeTrue()
        ->and($group->organization->id)->toBe($employee->organization_id)
        ->and($group->timeEntries)->toHaveCount(1)
        ->and($group->timeEntries->first()->is($entry))->toBeTrue();
});
