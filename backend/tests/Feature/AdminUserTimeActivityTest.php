<?php

use App\Http\Controllers\Api\AdminUserTimeActivityController;
use App\Models\QuickBooksToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

covers(AdminUserTimeActivityController::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-29 12:00:00');
    config([
        'quickbooks.time_activities_lookback_steps' => [365],
        'quickbooks.time_activities_lookback_days' => 365,
        'quickbooks.time_activities_list_cache_ttl_minutes' => 0,
    ]);
});

it('lists time activities for a provisioned timesheet user', function () {
    $admin = actingAsAdmin();
    $token = QuickBooksToken::factory()->forUser($admin)->create();

    $timesheetUser = timesheetUserFor($admin);
    seedListedTimeActivities($token, '7', 1);

    $this->actingAs($admin)
        ->getJson("/api/admin/users/{$timesheetUser->id}/time-activities?max_results=10", frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.Id', '1');
});

it('rejects listing time activities for an administrator account', function () {
    $admin = actingAsAdmin();
    $adminTarget = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->getJson("/api/admin/users/{$adminTarget->id}/time-activities", frontendHeaders())
        ->assertNotFound();
});

it('rejects updating synced time activities through ellr', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();
    $timesheetUser = timesheetUserFor($admin);

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$timesheetUser->id}/time-activities/12", [
            'is_billable' => true,
        ], frontendHeaders())
        ->assertNotFound();
});
