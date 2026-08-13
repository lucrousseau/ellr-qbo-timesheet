<?php

use App\Http\Controllers\Api\QuickBooksPickerController;
use App\Http\Controllers\Api\TimeTrackerController;
use App\Models\ActiveTimeSession;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Services\QboCustomerListService;
use App\Services\QboPickerValidationService;
use App\Services\QboProjectListService;
use App\Services\QboServiceListService;
use App\Services\QuickBooksService;
use App\Services\TimeTrackerService;
use Carbon\CarbonImmutable;
use QuickBooksOnline\API\DataService\DataService;

covers(QuickBooksPickerController::class);
covers(TimeTrackerController::class);
covers(QboCustomerListService::class);
covers(QboPickerValidationService::class);
covers(QboProjectListService::class);
covers(QboServiceListService::class);
covers(TimeTrackerService::class);

it('lists quickbooks customers assigned to the signed-in employee', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $employee = timesheetUserFor($admin);
    $employee->qboCustomers()->create([
        'qbo_customer_ref' => '11',
    ]);

    mockQboCustomerList();

    $this->actingAs($employee)
        ->getJson('/api/quickbooks/customers', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.id', '11')
        ->assertJsonPath('data.0.display_name', 'Acme Corp');
});

it('lists quickbooks projects for a parent customer', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $employee = timesheetUserFor($admin);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern('/Job = true/'))
        ->andReturn([
            (object) ['Id' => '22', 'DisplayName' => 'Website redesign', 'ParentRef' => (object) ['value' => '11']],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($employee)
        ->getJson('/api/quickbooks/projects?customer_ref=11', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.id', '22')
        ->assertJsonPath('data.0.display_name', 'Website redesign');
});

it('lists quickbooks services for authenticated timesheet users', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $employee = timesheetUserFor($admin);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) ['Id' => '33', 'Name' => 'Consulting'],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($employee)
        ->getJson('/api/quickbooks/services', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.id', '33')
        ->assertJsonPath('data.0.display_name', 'Consulting');
});

it('persists and restores active timer sessions per user', function () {
    $user = actingAsWithQboEmployee();

    $this->putJson('/api/time-tracker', [
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => null,
        'description' => 'Support',
        'is_running' => true,
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.description', 'Support')
        ->assertJsonPath('data.is_running', true);

    $this->actingAs($user)
        ->getJson('/api/time-tracker', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.description', 'Support')
        ->assertJsonPath('data.is_running', true);

    expect(ActiveTimeSession::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('returns timer sessions without picker refs without quickbooks sanitization', function () {
    $user = actingAsWithQboEmployee();

    ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => null,
        'description' => 'Support tickets',
        'accumulated_seconds' => 45,
        'running_since' => null,
    ]);

    $this->actingAs($user)
        ->getJson('/api/time-tracker', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.description', 'Support tickets')
        ->assertJsonPath('data.customer_ref', null)
        ->assertJsonPath('data.elapsed_seconds', 45);
});

it('does not sanitize timer sessions with blank picker refs on load', function () {
    $user = actingAsWithQboEmployee();

    ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => '   ',
        'project_ref' => null,
        'service_ref' => null,
        'description' => 'Draft',
        'accumulated_seconds' => 15,
        'running_since' => null,
    ]);

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldNotReceive('listForUser');
    });

    $this->actingAs($user)
        ->getJson('/api/time-tracker', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.customer_ref', '   ')
        ->assertJsonPath('data.customer_name', null);
});

it('clears unassigned customers from persisted timer sessions on load', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $employee = timesheetUserFor($admin);
    $employee->qboCustomers()->create([
        'qbo_customer_ref' => '12',
    ]);

    ActiveTimeSession::factory()->for($employee)->create([
        'customer_ref' => '11',
        'project_ref' => '22',
        'accumulated_seconds' => 120,
        'running_since' => null,
    ]);

    mockQboCustomerList([['id' => '12', 'display_name' => 'Beta LLC']]);

    $this->actingAs($employee)
        ->getJson('/api/time-tracker', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.customer_ref', null)
        ->assertJsonPath('data.customer_name', null)
        ->assertJsonPath('data.project_ref', null)
        ->assertJsonPath('data.project_name', null)
        ->assertJsonPath('data.elapsed_seconds', 120);

    $session = ActiveTimeSession::query()->where('user_id', $employee->id)->first();
    expect($session->customer_ref)->toBeNull()
        ->and($session->project_ref)->toBeNull();
});

it('rejects timer updates with customer refs that are not allowed for the employee', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = timesheetUserFor($admin);
    $this->actingAs($user);

    $this->putJson('/api/time-tracker', [
        'customer_ref' => '99',
        'project_ref' => null,
        'service_ref' => null,
        'description' => null,
        'is_running' => false,
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('message', __('api.time_tracker_invalid_customer'));
});

it('logs elapsed time as a draft local entry and clears the session', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = timesheetUserFor($admin);
    $this->actingAs($user);

    ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => null,
        'description' => 'Support call',
        'accumulated_seconds' => 3600,
        'running_since' => null,
    ]);

    $this->postJson('/api/time-tracker/log', [], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.description', 'Support call');

    expect(ActiveTimeSession::query()->where('user_id', $user->id)->exists())->toBeFalse();
    expect(TimeEntry::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('discards an active timer session', function () {
    $user = actingAsWithQboEmployee();
    ActiveTimeSession::factory()->for($user)->create();

    $this->deleteJson('/api/time-tracker', [], frontendHeaders())->assertNoContent();

    expect(ActiveTimeSession::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('returns null when no active timer session exists', function () {
    actingAsWithQboEmployee();

    $this->getJson('/api/time-tracker', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('pauses a running timer through the api', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    try {
        $user = actingAsWithQboEmployee();
        ActiveTimeSession::factory()->for($user)->create([
            'accumulated_seconds' => 120,
            'running_since' => '2026-07-28 11:59:00',
        ]);

        $this->putJson('/api/time-tracker', [
            'customer_ref' => null,
            'project_ref' => null,
            'service_ref' => null,
            'description' => null,
            'is_running' => false,
        ], frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.is_running', false)
            ->assertJsonPath('data.accumulated_seconds', 180);
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('rejects logging when the timer has no elapsed time', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = timesheetUserFor($admin);
    $this->actingAs($user);

    ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => null,
        'accumulated_seconds' => 0,
        'running_since' => null,
    ]);

    $this->postJson('/api/time-tracker/log', [], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('message', __('api.time_tracker_no_elapsed_time'));
});

it('rejects logging when no active timer session exists', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = timesheetUserFor($admin);
    $this->actingAs($user);

    $this->postJson('/api/time-tracker/log', [], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('message', __('api.time_tracker_empty'));
});

it('lists quickbooks customers with refresh enabled', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $employee = timesheetUserFor($admin);
    $employee->qboCustomers()->create([
        'qbo_customer_ref' => '11',
    ]);

    mockQboCustomerList();

    $this->actingAs($employee)
        ->getJson('/api/quickbooks/customers?refresh=1', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.id', '11')
        ->assertJsonPath('data.0.display_name', 'Acme Corp');
});

it('requires a customer reference for project picker requests', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $employee = timesheetUserFor($admin);

    $this->actingAs($employee)
        ->getJson('/api/quickbooks/projects', frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['customer_ref']);
});

it('updates timer state without quickbooks validation when picker refs are blank', function () {
    actingAsWithQboEmployee();

    $this->mock(QuickBooksService::class, function ($mock) {
        $mock->shouldNotReceive('dataService');
    });

    $this->putJson('/api/time-tracker', [
        'customer_ref' => '   ',
        'project_ref' => null,
        'service_ref' => null,
        'description' => 'Support',
        'is_running' => true,
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.description', 'Support');
});

it('rejects timer updates with invalid service refs', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = timesheetUserFor($admin);
    $this->actingAs($user);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->andReturn([
            (object) ['Id' => '33', 'Name' => 'Consulting'],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->putJson('/api/time-tracker', [
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => '99',
        'description' => null,
        'is_running' => false,
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('message', __('api.time_tracker_invalid_service'));
});

it('rejects timer updates with project refs but no customer', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = timesheetUserFor($admin);
    $this->actingAs($user);

    $this->putJson('/api/time-tracker', [
        'customer_ref' => null,
        'project_ref' => '22',
        'service_ref' => null,
        'description' => null,
        'is_running' => false,
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('message', __('api.time_tracker_invalid_project'));
});

it('updates accumulated seconds through the api', function () {
    $user = actingAsWithQboEmployee();
    ActiveTimeSession::factory()->for($user)->create([
        'accumulated_seconds' => 120,
        'running_since' => null,
    ]);

    $this->putJson('/api/time-tracker', [
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => null,
        'description' => null,
        'is_running' => false,
        'accumulated_seconds' => 300,
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.accumulated_seconds', 300)
        ->assertJsonPath('data.elapsed_seconds', 300);
});

it('updates billable flag through the api', function () {
    actingAsWithQboEmployee();

    $this->putJson('/api/time-tracker', [
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => null,
        'description' => null,
        'is_running' => false,
        'is_billable' => true,
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.is_billable', true);
});

it('preserves billable flag when update omits is_billable', function () {
    $user = actingAsWithQboEmployee();

    $this->putJson('/api/time-tracker', [
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => null,
        'description' => null,
        'is_running' => false,
        'is_billable' => true,
    ], frontendHeaders())
        ->assertOk();

    $this->actingAs($user)
        ->putJson('/api/time-tracker', [
            'customer_ref' => null,
            'project_ref' => null,
            'service_ref' => null,
            'description' => 'Support',
            'is_running' => false,
        ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.is_billable', true)
        ->assertJsonPath('data.description', 'Support');
});

it('rejects accumulated seconds above the configured maximum', function () {
    config(['quickbooks.time_tracker_max_accumulated_seconds' => 300]);
    actingAsWithQboEmployee();

    $this->putJson('/api/time-tracker', [
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => null,
        'description' => null,
        'is_running' => false,
        'accumulated_seconds' => 301,
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['accumulated_seconds']);
});
