<?php

/**
 * Feature coverage for sync group drill-down API used by QBO note deep links.
 */

use App\Enums\TimeEntryStatus;
use App\Http\Controllers\Api\TimeEntrySyncGroupController;
use App\Models\TimeEntry;
use App\Models\TimeEntrySyncGroup;

covers(TimeEntrySyncGroupController::class);

beforeEach(function () {
    mockQboEntryDisplayNames(['item_name' => 'Programming']);
});

it('returns sync group details for an administrator deep link', function () {
    $admin = actingAsAdmin();
    $employee = timesheetUserFor($admin);
    $group = TimeEntrySyncGroup::factory()->forUser($employee)->create([
        'member_count' => 1,
        'total_duration_seconds' => 3600,
        'qbo_id' => '900',
        'synced_at' => now(),
    ]);
    TimeEntry::factory()->forUser($employee)->create([
        'status' => TimeEntryStatus::Approved,
        'qbo_id' => '900',
        'sync_group_id' => $group->id,
        'description' => 'Grouped detail',
        'start_time' => now()->subHour(),
        'end_time' => now(),
    ]);

    $this->getJson('/api/time-entry-sync-groups/'.$group->public_id)
        ->assertOk()
        ->assertJsonPath('data.public_id', $group->public_id)
        ->assertJsonPath('data.qbo_id', '900')
        ->assertJsonPath('data.entries.0.description', 'Grouped detail');
});

it('returns not found for an unknown sync group', function () {
    actingAsAdmin();

    $this->getJson('/api/time-entry-sync-groups/00000000-0000-4000-8000-000000000000')
        ->assertNotFound()
        ->assertJsonPath('error', 'time_entry_sync_group_not_found');
});
