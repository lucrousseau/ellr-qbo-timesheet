<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Notifications\InviteTimesheetUserNotification;
use App\Services\QuickBooksService;
use App\Services\UserProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use QuickBooksOnline\API\DataService\DataService;

covers(UserProvisioningService::class);

uses(RefreshDatabase::class);

it('lists only non-administrator users with a quickbooks employee mapping', function () {
    User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    User::factory()->admin()->create([
        'qbo_employee_ref' => '9',
    ]);
    User::factory()->create();

    $users = app(UserProvisioningService::class)->listTimesheetUsers();

    expect($users)->toHaveCount(1)
        ->and($users->first()->qbo_employee_ref)->toBe('7');
});

it('provisions a timesheet user and sends an invitation email', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create(['locale' => 'fr']);
    QuickBooksToken::factory()->forUser($admin)->create();

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

    $created = app(UserProvisioningService::class)->provisionTimesheetUser($admin, [
        'qbo_employee_ref' => 42,
    ]);

    expect($created->email)->toBe('jane@example.com')
        ->and($created->locale)->toBe('fr')
        ->and($created->qbo_employee_ref)->toBe('42')
        ->and($created->isAdmin())->toBeFalse();

    Notification::assertSentTo($created, InviteTimesheetUserNotification::class);
});
