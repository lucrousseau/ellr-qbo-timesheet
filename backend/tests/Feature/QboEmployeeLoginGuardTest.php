<?php

use App\Http\Controllers\Api\AuthController;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboEmployeeLoginGuardService;
use App\Services\QboEmployeeService;
use App\Services\QuickBooksService;
use QuickBooksOnline\API\DataService\DataService;

covers(AuthController::class);
covers(QboEmployeeLoginGuardService::class);
covers(QboEmployeeService::class);

it('rejects login when the mapped quickbooks employee no longer exists', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '7')->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => validTestPassword(),
    ], frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'qbo_employee_removed');

    $this->assertGuest();
});

it('allows login when the mapped quickbooks employee still exists', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '7')->andReturn((object) [
        'Id' => '7',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => $user->email],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => validTestPassword(),
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);

    $this->assertAuthenticatedAs($user);
});

it('syncs name and email from quickbooks on login', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();

    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Old Name',
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '7')->andReturn((object) [
        'Id' => '7',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => validTestPassword(),
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.name', 'Jane Doe')
        ->assertJsonPath('user.email', 'jane@example.com');

    expect($user->fresh()->email)->toBe('jane@example.com');
});

it('rejects login when the quickbooks employee has no email', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '7')->andReturn((object) [
        'Id' => '7',
        'DisplayName' => 'Jane Doe',
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => validTestPassword(),
    ], frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'qbo_employee_email_missing');
});

it('rejects login when quickbooks identity sync hits an email conflict', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();

    User::factory()->create(['email' => 'jane@example.com']);
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Old Name',
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '7')->andReturn((object) [
        'Id' => '7',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => validTestPassword(),
    ], frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'qbo_employee_email_conflict');

    $this->assertGuest();
});

it('does not check quickbooks employees for administrator logins', function () {
    $admin = User::factory()->admin()->create();

    $this->mock(QuickBooksService::class, function ($mock) {
        $mock->shouldReceive('dataService')->never();
    });

    $this->postJson('/api/login', [
        'email' => $admin->email,
        'password' => validTestPassword(),
    ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.id', $admin->id);
});
