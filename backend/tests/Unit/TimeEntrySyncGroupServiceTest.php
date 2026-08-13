<?php

/**
 * Tests for sync group drill-down authorization and payload shape.
 */

use App\Enums\TimeEntryStatus;
use App\Models\TimeEntry;
use App\Models\TimeEntrySyncGroup;
use App\Models\User;
use App\Services\TimeEntrySyncGroupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(TimeEntrySyncGroupService::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    mockQboEntryDisplayNames(['item_name' => 'Programming']);
    config(['app.frontend_admin_url' => 'http://admin.test']);
});

it('returns sync group members for administrators', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
        'name' => 'Pat Programmer',
    ]);
    $group = TimeEntrySyncGroup::factory()->forUser($employee)->create([
        'txn_date' => '2026-08-12',
        'customer_ref' => '10',
        'project_ref' => '20',
        'item_ref' => '33',
        'is_billable' => true,
        'member_count' => 2,
        'total_duration_seconds' => 7200,
        'qbo_id' => '900',
        'qbo_description' => 'Ellr grouped time',
        'synced_at' => '2026-08-12 18:00:00',
    ]);
    $afternoon = TimeEntry::factory()->forUser($employee)->create([
        'status' => TimeEntryStatus::Approved,
        'item_ref' => '33',
        'description' => 'Afternoon',
        'qbo_id' => '900',
        'sync_group_id' => $group->id,
        'start_time' => now()->startOfDay()->addHours(13),
        'end_time' => now()->startOfDay()->addHours(14),
    ]);
    $morning = TimeEntry::factory()->forUser($employee)->create([
        'status' => TimeEntryStatus::Approved,
        'item_ref' => '33',
        'description' => 'Morning',
        'qbo_id' => '900',
        'sync_group_id' => $group->id,
        'start_time' => now()->startOfDay()->addHours(9),
        'end_time' => now()->startOfDay()->addHours(10),
    ]);

    $payload = app(TimeEntrySyncGroupService::class)->showForActor($admin, $group->public_id);

    expect($payload['data'])->toMatchArray([
        'id' => $group->id,
        'public_id' => $group->public_id,
        'user_id' => $employee->id,
        'employee_name' => 'Pat Programmer',
        'txn_date' => '2026-08-12',
        'customer_ref' => '10',
        'project_ref' => '20',
        'item_ref' => '33',
        'is_billable' => true,
        'member_count' => 2,
        'total_duration_seconds' => 7200,
        'qbo_id' => '900',
        'qbo_description' => 'Ellr grouped time',
        'detail_url' => 'http://admin.test/?sync_group='.$group->public_id,
    ])
        ->and($payload['data']['synced_at'])->not->toBeNull()
        ->and($payload['data']['entries'])->toHaveCount(2)
        ->and($payload['data']['entries'][0]['id'])->toBe($morning->id)
        ->and($payload['data']['entries'][0]['description'])->toBe('Morning')
        ->and($payload['data']['entries'][1]['id'])->toBe($afternoon->id)
        ->and($payload['data']['entries'][1]['description'])->toBe('Afternoon');
});

it('allows the employee and their supervisor to view the group', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->create(['organization_id' => $admin->organization_id]);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'supervisor_id' => $supervisor->id,
        'qbo_employee_ref' => '7',
    ]);
    $group = TimeEntrySyncGroup::factory()->forUser($employee)->create([
        'qbo_id' => '901',
        'synced_at' => now(),
    ]);

    $asEmployee = app(TimeEntrySyncGroupService::class)->showForActor($employee, $group->public_id);
    $asSupervisor = app(TimeEntrySyncGroupService::class)->showForActor($supervisor, $group->public_id);

    expect($asEmployee['data']['public_id'])->toBe($group->public_id)
        ->and($asSupervisor['data']['public_id'])->toBe($group->public_id);
});

it('forbids unrelated users from viewing a sync group', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $stranger = User::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);
    $group = TimeEntrySyncGroup::factory()->forUser($employee)->create();

    app(TimeEntrySyncGroupService::class)->showForActor($stranger, $group->public_id);
})->throws(HttpResponseException::class);

it('returns not found for an unknown public id', function () {
    $admin = User::factory()->admin()->create();

    app(TimeEntrySyncGroupService::class)->showForActor(
        $admin,
        '00000000-0000-4000-8000-000000000000',
    );
})->throws(HttpResponseException::class);
