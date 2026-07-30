<?php

use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Notifications\InviteTimesheetUserNotification;
use App\Services\QuickBooksService;
use App\Services\UserProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use QuickBooksOnline\API\DataService\DataService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

covers(UserProvisioningService::class);

uses(RefreshDatabase::class);

it('lists only non-administrator users with a quickbooks employee mapping in the same organization', function () {
    $organization = Organization::factory()->create();
    $otherOrganization = Organization::factory()->create();
    $admin = User::factory()->admin()->create(['organization_id' => $organization->id]);

    User::factory()->create([
        'organization_id' => $organization->id,
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    User::factory()->create([
        'organization_id' => $otherOrganization->id,
        'qbo_employee_ref' => '8',
        'qbo_employee_name' => 'Other Org User',
    ]);
    User::factory()->admin()->create([
        'organization_id' => $organization->id,
        'qbo_employee_ref' => '9',
    ]);
    User::factory()->create(['organization_id' => $organization->id]);

    $users = app(UserProvisioningService::class)->listTimesheetUsers($admin);

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
        ->and($created->userLevel->code)->toBe('employee')
        ->and($created->organization_id)->toBe($admin->organization_id)
        ->and($created->organization_id)->toBe($admin->fresh()->organization_id)
        ->and($created->qbo_employee_ref)->toBe('42')
        ->and($created->isAdmin())->toBeFalse();

    Notification::assertSentTo($created, InviteTimesheetUserNotification::class);
});

it('revokes a provisioned timesheet user account', function () {
    $admin = User::factory()->admin()->create();
    $timesheetUser = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $timesheetUser->createToken('timesheet');

    app(UserProvisioningService::class)->revokeTimesheetUser($admin, $timesheetUser);

    expect(User::query()->find($timesheetUser->id))->toBeNull()
        ->and($timesheetUser->tokens()->count())->toBe(0);
});

it('rejects revoking a user without a quickbooks employee mapping', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['organization_id' => $admin->organization_id]);

    expect(fn () => app(UserProvisioningService::class)->revokeTimesheetUser($admin, $user))
        ->toThrow(NotFoundHttpException::class);
});

it('rejects revoking a user from another organization', function () {
    $admin = User::factory()->admin()->create();
    $timesheetUser = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    expect(fn () => app(UserProvisioningService::class)->revokeTimesheetUser($admin, $timesheetUser))
        ->toThrow(NotFoundHttpException::class);
});

it('clears password reset tokens when revoking a timesheet user', function () {
    $admin = User::factory()->admin()->create();
    $timesheetUser = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    DB::table('password_reset_tokens')->insert([
        'email' => $timesheetUser->email,
        'token' => 'reset-token',
        'created_at' => now(),
    ]);

    app(UserProvisioningService::class)->revokeTimesheetUser($admin, $timesheetUser);

    expect(DB::table('password_reset_tokens')->where('email', $timesheetUser->email)->count())->toBe(0);
});

it('rejects revoking a user with an empty quickbooks employee mapping', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '',
    ]);

    expect(fn () => app(UserProvisioningService::class)->revokeTimesheetUser($admin, $user))
        ->toThrow(NotFoundHttpException::class);
});
