<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Notifications\InviteTimesheetUserNotification;
use App\Services\QboEmployeeService;
use App\Services\QuickBooksService;
use App\Services\TimesheetInvitationService;
use App\Services\UserProvisioningService;
use App\Support\UserApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use QuickBooksOnline\API\DataService\DataService;

covers(AdminUserController::class);
covers(UserApiResponse::class);
covers(UserProvisioningService::class);
covers(QboEmployeeService::class);
covers(TimesheetInvitationService::class);

function mockQuickBooksEmployee(DataService $dataService, string $ref, ?array $employee = null): void
{
    if ($employee === null) {
        $dataService->shouldReceive('FindById')->with('Employee', $ref)->andReturn(null);

        return;
    }

    $dataService->shouldReceive('FindById')->with('Employee', $ref)->andReturn((object) [
        'Id' => $ref,
        'DisplayName' => $employee['display_name'],
        'PrimaryEmailAddr' => isset($employee['email'])
            ? (object) ['Address' => $employee['email']]
            : null,
    ]);
}

it('lists provisioned timesheet users for administrators', function () {
    $admin = actingAsAdmin();
    $timesheetUser = timesheetUserFor($admin);

    $this->actingAs($admin)
        ->getJson('/api/admin/users', frontendHeaders())
        ->assertOk()
        ->assertJsonPath('data.0.id', $timesheetUser->id)
        ->assertJsonPath('data.0.qbo_employee_ref', '7')
        ->assertJsonPath('data.0.level', 'employee');
});

it('requires administrator access to list timesheet users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/admin/users', frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'admin_required');
});

it('creates a timesheet user from quickbooks identity and sends an invite email', function () {
    Notification::fake();

    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $dataService = Mockery::mock(DataService::class);
    $employeeObject = (object) [
        'Id' => '42',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ];
    $dataService->shouldReceive('FindById')->twice()->with('Employee', '42')->andReturn($employeeObject);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->postJson('/api/admin/users', [
            'qbo_employee_ref' => '42',
        ], frontendHeaders())
        ->assertCreated()
        ->assertJsonPath('user.email', 'jane@example.com')
        ->assertJsonPath('user.name', 'Jane Doe')
        ->assertJsonPath('user.qbo_employee_ref', '42')
        ->assertJsonPath('user.level', 'employee');

    $created = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($created->isAdmin())->toBeFalse()
        ->and($created->hasVerifiedEmail())->toBeTrue()
        ->and($created->userLevel->code)->toBe('employee');

    Notification::assertSentTo($created, InviteTimesheetUserNotification::class);
});

it('validates timesheet user provisioning payload', function () {
    $admin = actingAsAdmin();

    $this->actingAs($admin)
        ->postJson('/api/admin/users', [], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['qbo_employee_ref']);
});

it('rejects provisioning when the quickbooks employee does not exist', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $dataService = Mockery::mock(DataService::class);
    mockQuickBooksEmployee($dataService, '999');
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->postJson('/api/admin/users', [
            'qbo_employee_ref' => '999',
        ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('error', 'qbo_employee_invalid');
});

it('rejects provisioning when the quickbooks employee has no email', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $dataService = Mockery::mock(DataService::class);
    mockQuickBooksEmployee($dataService, '42', [
        'display_name' => 'Jane Doe',
        'email' => null,
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->postJson('/api/admin/users', [
            'qbo_employee_ref' => '42',
        ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('error', 'qbo_employee_email_missing');
});

it('rejects provisioning when the quickbooks employee email is already used', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    User::factory()->create(['email' => 'jane@example.com']);

    $dataService = Mockery::mock(DataService::class);
    mockQuickBooksEmployee($dataService, '42', [
        'display_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->postJson('/api/admin/users', [
            'qbo_employee_ref' => '42',
        ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJson([
            'error' => 'qbo_employee_email_conflict',
            'message' => 'QuickBooks employee email is already used by another account.',
        ]);
});

it('rejects duplicate quickbooks employee provisioning in the same organization', function () {
    $admin = actingAsAdmin();
    timesheetUserFor($admin, ['qbo_employee_ref' => '42']);

    $this->actingAs($admin)
        ->postJson('/api/admin/users', [
            'qbo_employee_ref' => '42',
        ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['qbo_employee_ref']);
});

it('allows the same quickbooks employee ref in a different organization', function () {
    Notification::fake();

    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    User::factory()->create(['qbo_employee_ref' => '42']);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->twice()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->postJson('/api/admin/users', [
            'qbo_employee_ref' => '42',
        ], frontendHeaders())
        ->assertCreated()
        ->assertJsonPath('user.qbo_employee_ref', '42');
});

it('removes a provisioned timesheet user and revokes their sessions', function () {
    $admin = actingAsAdmin();
    $timesheetUser = timesheetUserFor($admin);
    $timesheetUser->createToken('timesheet');

    $this->actingAs($admin)
        ->deleteJson("/api/admin/users/{$timesheetUser->id}", [], frontendHeaders())
        ->assertNoContent();

    expect(User::query()->find($timesheetUser->id))->toBeNull()
        ->and($timesheetUser->tokens()->count())->toBe(0);
});

it('requires administrator access to remove a timesheet user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);
    $timesheetUser = timesheetUserFor($admin);

    $this->actingAs($user)
        ->deleteJson("/api/admin/users/{$timesheetUser->id}", [], frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'admin_required');

    expect(User::query()->find($timesheetUser->id))->not->toBeNull();
});

it('returns not found when removing a non-provisioned user', function () {
    $admin = actingAsAdmin();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->deleteJson("/api/admin/users/{$user->id}", [], frontendHeaders())
        ->assertNotFound();
});

it('returns not found when removing an administrator account', function () {
    $admin = actingAsAdmin();
    $adminTarget = User::factory()->admin()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/admin/users/{$adminTarget->id}", [], frontendHeaders())
        ->assertNotFound();

    expect(User::query()->find($adminTarget->id))->not->toBeNull();
});

it('removes database sessions and password reset tokens when revoking a timesheet user', function () {
    config()->set('session.driver', 'database');

    $admin = actingAsAdmin();
    $timesheetUser = timesheetUserFor($admin);

    DB::table('sessions')->insert([
        'id' => 'session-to-revoke',
        'user_id' => $timesheetUser->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    DB::table('password_reset_tokens')->insert([
        'email' => $timesheetUser->email,
        'token' => 'reset-token',
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/admin/users/{$timesheetUser->id}", [], frontendHeaders())
        ->assertNoContent();

    expect(User::query()->find($timesheetUser->id))->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $timesheetUser->id)->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->where('email', $timesheetUser->email)->count())->toBe(0);
});
