<?php

use App\Http\Controllers\Api\AdminQboEmployeeController;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use QuickBooksOnline\API\DataService\DataService;

covers(AdminQboEmployeeController::class);

it('allows administrators to map a qbo employee for another user', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();
    $target = User::factory()->create();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$target->id}/qbo-employee", [
            'qbo_employee_ref' => '42',
        ], frontendHeaders())
        ->assertOk()
        ->assertJsonPath('user.qbo_employee_ref', '42')
        ->assertJsonPath('user.name', 'Jane Doe')
        ->assertJsonPath('user.email', 'jane@example.com');

    expect($target->fresh()->qbo_employee_ref)->toBe('42');
});

it('requires administrator access to map employees for other users', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($user)
        ->patchJson("/api/admin/users/{$target->id}/qbo-employee", [
            'qbo_employee_ref' => '42',
        ], frontendHeaders())
        ->assertForbidden()
        ->assertJsonPath('error', 'admin_required');
});

it('rejects unknown qbo employees when mapping another user', function () {
    $admin = actingAsAdmin();
    QuickBooksToken::factory()->forUser($admin)->create();
    $target = User::factory()->create();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '999')->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $this->actingAs($admin)
        ->patchJson("/api/admin/users/{$target->id}/qbo-employee", [
            'qbo_employee_ref' => '999',
        ], frontendHeaders())
        ->assertUnprocessable()
        ->assertJsonPath('error', 'qbo_employee_invalid');
});
