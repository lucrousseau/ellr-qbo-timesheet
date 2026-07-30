<?php

use App\Http\Controllers\Api\AdminQuickBooksPickerController;
use App\Http\Controllers\Api\AdminUserCustomerController;
use App\Models\ActiveTimeSession;
use App\Models\QuickBooksToken;
use App\Services\QboCustomerListService;
use App\Services\QuickBooksService;
use App\Services\UserQboCustomerAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use QuickBooksOnline\API\DataService\DataService;

uses(RefreshDatabase::class);

covers(AdminUserCustomerController::class);
covers(AdminQuickBooksPickerController::class);
covers(UserQboCustomerAssignmentService::class);

it('lists assigned customers for a provisioned timesheet user', function () {
    $admin = actingAsAdmin();
    $timesheetUser = timesheetUserFor($admin);
    $timesheetUser->qboCustomers()->create([
        'qbo_customer_ref' => '11',
        'qbo_customer_name' => 'Acme Corp',
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin/users/{$timesheetUser->id}/customers", frontendHeaders())
        ->assertOk()
        ->assertJsonPath('all_customers_access', false)
        ->assertJsonPath('data.0.id', '11')
        ->assertJsonPath('data.0.display_name', 'Acme Corp');
});

it('reports all-customers access for a provisioned timesheet user', function () {
    $admin = actingAsAdmin();
    $timesheetUser = timesheetUserFor($admin, [
        'qbo_all_customers_access' => true,
    ]);

    $this->actingAs($admin)
        ->getJson("/api/admin/users/{$timesheetUser->id}/customers", frontendHeaders())
        ->assertOk()
        ->assertJsonPath('all_customers_access', true)
        ->assertJsonPath('data', []);
});

it('syncs customer assignments for a provisioned timesheet user', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $timesheetUser = timesheetUserFor($admin);

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldReceive('listAllActive')
            ->once()
            ->with(Mockery::any(), true)
            ->andReturn([
                ['id' => '11', 'display_name' => 'Acme Corp'],
                ['id' => '12', 'display_name' => 'Beta LLC'],
            ]);
    });

    $this->actingAs($admin)
        ->putJson("/api/admin/users/{$timesheetUser->id}/customers", [
            'all_customers_access' => false,
            'customer_refs' => ['11', '12'],
        ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('all_customers_access', false)
        ->assertJsonCount(2, 'data');

    expect($timesheetUser->fresh()->qboCustomers)->toHaveCount(2);
});

it('grants all-customers access for a provisioned timesheet user', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $timesheetUser = timesheetUserFor($admin);
    $timesheetUser->qboCustomers()->create([
        'qbo_customer_ref' => '11',
        'qbo_customer_name' => 'Acme Corp',
    ]);

    $this->actingAs($admin)
        ->putJson("/api/admin/users/{$timesheetUser->id}/customers", [
            'all_customers_access' => true,
            'customer_refs' => [],
        ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('all_customers_access', true)
        ->assertJsonPath('data', []);

    $timesheetUser = $timesheetUser->fresh();
    expect($timesheetUser->qbo_all_customers_access)->toBeTrue()
        ->and($timesheetUser->qboCustomers)->toHaveCount(0);
});

it('rejects invalid customer assignments', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $timesheetUser = timesheetUserFor($admin);

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldReceive('listAllActive')
            ->once()
            ->with(Mockery::any(), true)
            ->andReturn([
                ['id' => '11', 'display_name' => 'Acme Corp'],
            ]);
    });

    $this->actingAs($admin)
        ->putJson("/api/admin/users/{$timesheetUser->id}/customers", [
            'all_customers_access' => false,
            'customer_refs' => ['11', '99'],
        ], frontendHeaders())
        ->assertStatus(422);
});

it('returns not found when reading customer access for a non-timesheet user', function () {
    $admin = actingAsAdmin();

    $this->actingAs($admin)
        ->getJson('/api/admin/users/'.$admin->id.'/customers', frontendHeaders())
        ->assertNotFound();
});

it('returns not found when updating customer access for a non-timesheet user', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $this->actingAs($admin)
        ->putJson('/api/admin/users/'.$admin->id.'/customers', [
            'all_customers_access' => true,
            'customer_refs' => [],
        ], frontendHeaders())
        ->assertNotFound();
});

it('clears stale timer customer selections when admin updates assignments', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $timesheetUser = timesheetUserFor($admin);

    ActiveTimeSession::factory()->for($timesheetUser)->create([
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website redesign',
        'accumulated_seconds' => 120,
        'running_since' => null,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern('/FROM Customer WHERE Active = true AND Job = false/'))
        ->andReturn([
            (object) ['Id' => '12', 'DisplayName' => 'Beta LLC'],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->putJson("/api/admin/users/{$timesheetUser->id}/customers", [
            'all_customers_access' => false,
            'customer_refs' => ['12'],
        ], frontendHeaders())
        ->assertOk();

    $session = ActiveTimeSession::query()->where('user_id', $timesheetUser->id)->first();
    expect($session->customer_ref)->toBeNull()
        ->and($session->project_ref)->toBeNull()
        ->and($session->accumulated_seconds)->toBe(120);
});

it('lists all quickbooks customers for administrators', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldReceive('listAllActive')
            ->once()
            ->andReturn([
                ['id' => '11', 'display_name' => 'Acme Corp'],
            ]);
    });

    $this->actingAs($admin)
        ->getJson('/api/admin/quickbooks/customers?refresh=1', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.id', '11');
});

it('lists quickbooks customers for administrators without forcing refresh', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $this->mock(QboCustomerListService::class, function ($mock) {
        $mock->shouldReceive('listAllActive')
            ->once()
            ->with(Mockery::any(), false)
            ->andReturn([
                ['id' => '11', 'display_name' => 'Acme Corp'],
            ]);
    });

    $this->actingAs($admin)
        ->getJson('/api/admin/quickbooks/customers', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.display_name', 'Acme Corp');
});

it('includes assigned customers when listing provisioned users', function () {
    $admin = actingAsAdmin();
    $timesheetUser = timesheetUserFor($admin);
    $timesheetUser->qboCustomers()->create([
        'qbo_customer_ref' => '11',
        'qbo_customer_name' => 'Acme Corp',
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/users', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.assigned_customers.0.id', '11')
        ->assertJsonPath('data.0.all_customers_access', false);
});

it('includes all-customers access when listing provisioned users', function () {
    $admin = actingAsAdmin();
    $timesheetUser = timesheetUserFor($admin, [
        'qbo_all_customers_access' => true,
    ]);

    $this->actingAs($admin)
        ->getJson('/api/admin/users', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.all_customers_access', true)
        ->assertJsonPath('data.0.assigned_customers', []);
});

it('lists stored customer assignments in the timesheet picker without quickbooks id lookups', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $user = timesheetUserFor($admin);
    $user->qboCustomers()->create([
        'qbo_customer_ref' => '11',
        'qbo_customer_name' => 'Acme Corp',
    ]);

    $this->actingAs($user)
        ->getJson('/api/quickbooks/customers', frontendHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', '11')
        ->assertJsonPath('data.0.display_name', 'Acme Corp');
});

it('lists every quickbooks customer when all-customers access is enabled', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);

    $employee = timesheetUserFor($admin, [
        'qbo_all_customers_access' => true,
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern('/FROM Customer WHERE Active = true AND Job = false/'))
        ->andReturn([
            (object) ['Id' => '11', 'DisplayName' => 'Acme Corp'],
            (object) ['Id' => '12', 'DisplayName' => 'Beta LLC'],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($employee)
        ->getJson('/api/quickbooks/customers', frontendHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'data');
});
