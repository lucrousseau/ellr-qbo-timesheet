<?php

use App\Http\Controllers\Api\QboEmployeeController;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use QuickBooksOnline\API\DataService\DataService;

covers(QboEmployeeController::class);

it('updates the authenticated administrators qbo employee mapping', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) ['Id' => '42']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->patchJson('/api/user/qbo-employee', [
            'qbo_employee_ref' => '42',
            'qbo_employee_name' => 'Jane Doe',
        ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.qbo_employee_ref', '42')
        ->assertJsonPath('user.qbo_employee_name', 'Jane Doe');

    expect($admin->fresh()->qbo_employee_ref)->toBe('42');
});

it('requires administrator access to update qbo employee mapping', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson('/api/user/qbo-employee', [
            'qbo_employee_ref' => '42',
        ], frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'admin_required');
});

it('validates qbo employee payload', function () {
    $admin = actingAsAdmin();

    $this->actingAs($admin)
        ->patchJson('/api/user/qbo-employee', [], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['qbo_employee_ref']);
});

it('rejects unknown qbo employees', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '999')->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->patchJson('/api/user/qbo-employee', [
            'qbo_employee_ref' => '999',
        ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('error', 'qbo_employee_invalid');
});

it('rejects duplicate qbo employee mappings', function () {
    $admin = actingAsAdmin();
    User::factory()->create(['qbo_employee_ref' => '42']);

    $this->actingAs($admin)
        ->patchJson('/api/user/qbo-employee', [
            'qbo_employee_ref' => '42',
        ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['qbo_employee_ref']);
});
