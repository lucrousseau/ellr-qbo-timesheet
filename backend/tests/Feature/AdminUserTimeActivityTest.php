<?php

use App\Http\Controllers\Api\AdminUserTimeActivityController;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use QuickBooksOnline\API\Data\IPPTimeActivity;
use QuickBooksOnline\API\DataService\DataService;
use Tests\Support\MockeryCapture;

uses(RefreshDatabase::class);

covers(AdminUserTimeActivityController::class);

const ADMIN_TIME_ACTIVITY_LIST_QUERY = "SELECT * FROM TimeActivity WHERE TxnDate >= '2025-07-29' STARTPOSITION 1 MAXRESULTS 100";

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

it('updates a provisioned user time activity billable status', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $timesheetUser = timesheetUserFor($admin);

    $existing = new IPPTimeActivity;
    $existing->Id = '12';
    $existing->SyncToken = '1';
    $existing->StartTime = '2026-07-27T09:00:00';
    $existing->EndTime = '2026-07-27T18:00:00';
    $existing->EmployeeRef = (object) ['value' => '7'];
    $captured = null;
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->twice()->with('TimeActivity', '12')->andReturn($existing);
    $dataService->shouldReceive('Update')
        ->once()
        ->with(Mockery::capture($captured))
        ->andReturn((object) ['Id' => '12']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$timesheetUser->id}/time-activities/12", [
            'is_billable' => true,
        ], frontendHeaders())
        ->assertOk();

    expect(MockeryCapture::unwrap($captured)->BillableStatus->value)->toBe('Billable');
});

it('rejects updating time activities for another employee', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $timesheetUser = timesheetUserFor($admin);

    $activity = (object) [
        'Id' => '1',
        'EmployeeRef' => (object) ['value' => '99'],
    ];
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('TimeActivity', '1')->andReturn($activity);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->once()->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$timesheetUser->id}/time-activities/1", [
            'description' => 'Updated',
        ], frontendHeaders())
        ->assertNotFound();
});
