<?php

use App\Http\Controllers\Api\QuickBooksPickerController;
use App\Http\Controllers\Api\TimeTrackerController;
use App\Models\ActiveTimeSession;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboCustomerListService;
use App\Services\QboPickerValidationService;
use App\Services\QboProjectListService;
use App\Services\QboServiceListService;
use App\Services\QuickBooksService;
use App\Services\TimeTrackerService;
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

    $employee = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $employee->qboCustomers()->create([
        'qbo_customer_ref' => '11',
        'qbo_customer_name' => 'Acme Corp',
    ]);

    $this->actingAs($employee)
        ->getJson('/api/quickbooks/customers', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.id', '11')
        ->assertJsonPath('data.0.display_name', 'Acme Corp');
});

it('lists quickbooks projects for a parent customer', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $employee = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern("/ParentRef = '11'/"))
        ->andReturn([
            (object) ['Id' => '22', 'DisplayName' => 'Website redesign'],
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

    $employee = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

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
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
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

it('clears unassigned customers from persisted timer sessions on load', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $employee = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $employee->qboCustomers()->create([
        'qbo_customer_ref' => '12',
        'qbo_customer_name' => 'Beta LLC',
    ]);

    ActiveTimeSession::factory()->for($employee)->create([
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website redesign',
        'accumulated_seconds' => 120,
        'running_since' => null,
    ]);

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
    actingAsWithQboEmployee();

    $this->putJson('/api/time-tracker', [
        'customer_ref' => '99',
        'customer_name' => 'Unknown Corp',
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'description' => null,
        'is_running' => false,
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('message', __('api.time_tracker_invalid_customer'));
});

it('logs elapsed time to quickbooks and clears the session', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = actingAsWithQboEmployee();

    ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'description' => 'Support call',
        'accumulated_seconds' => 3600,
        'running_since' => null,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')
        ->once()
        ->andReturn((object) ['Id' => '99']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->postJson('/api/time-tracker/log', [], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.Id', '99');

    expect(ActiveTimeSession::query()->where('user_id', $user->id)->exists())->toBeFalse();
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
    $user = actingAsWithQboEmployee();
    ActiveTimeSession::factory()->for($user)->create([
        'accumulated_seconds' => 120,
        'running_since' => now()->subMinute(),
    ]);

    $this->putJson('/api/time-tracker', [
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'description' => null,
        'is_running' => false,
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.is_running', false)
        ->assertJsonPath('data.accumulated_seconds', 180);
});

it('rejects logging when the timer has no elapsed time', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = actingAsWithQboEmployee();

    ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
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
    actingAsWithQboEmployee();

    $this->postJson('/api/time-tracker/log', [], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('message', __('api.time_tracker_empty'));
});

it('lists quickbooks customers with refresh enabled', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $employee = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $employee->qboCustomers()->create([
        'qbo_customer_ref' => '11',
        'qbo_customer_name' => 'Acme Corp',
    ]);

    $this->actingAs($employee)
        ->getJson('/api/quickbooks/customers?refresh=1', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.id', '11')
        ->assertJsonPath('data.0.display_name', 'Acme Corp');
});

it('requires a customer reference for project picker requests', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $employee = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

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
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'description' => 'Support',
        'is_running' => true,
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.description', 'Support');
});

it('rejects timer updates with invalid service refs', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    actingAsWithQboEmployee();

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
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => '99',
        'service_name' => 'Unknown service',
        'description' => null,
        'is_running' => false,
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('message', __('api.time_tracker_invalid_service'));
});

it('rejects timer updates with project refs but no customer', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    actingAsWithQboEmployee();

    $this->putJson('/api/time-tracker', [
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => '22',
        'project_name' => 'Website',
        'service_ref' => null,
        'service_name' => null,
        'description' => null,
        'is_running' => false,
    ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('message', __('api.time_tracker_invalid_project'));
});
