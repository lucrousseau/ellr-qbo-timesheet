<?php

use App\Exceptions\QuickBooksOAuthException;
use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\OrganizationRealmService;
use App\Services\QboEmployeeListService;
use App\Services\QuickBooksConnectionValidationService;
use App\Services\QuickBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(QuickBooksConnectionValidationService::class);

uses(RefreshDatabase::class);

it('allows different organizations to connect different quickbooks realms', function () {
    $existingAdmin = User::factory()->admin()->create();
    $existingAdmin->organization->update(['realm_id' => '111']);
    QuickBooksToken::factory()->forUser($existingAdmin)->create(['realm_id' => '111']);

    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => '222']);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('disconnect')->never();

    $employeeList = Mockery::mock(QboEmployeeListService::class);
    $employeeList->shouldReceive('assertCanListEmployees')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->is($token)));

    (new QuickBooksConnectionValidationService(
        $quickBooks,
        $employeeList,
        app(OrganizationRealmService::class),
    ))->validateAdministratorConnection($admin, $token);

    expect($admin->organization->fresh()->realm_id)->toBe('222');
});

it('rejects oauth when the organization already connected a different realm', function () {
    $admin = User::factory()->admin()->create();
    $admin->organization->update(['realm_id' => '111']);
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => '222']);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('disconnect')->once()->with(Mockery::on(fn ($arg) => $arg->is($admin)));

    $employeeList = Mockery::mock(QboEmployeeListService::class);
    $employeeList->shouldReceive('assertCanListEmployees')->never();

    expect(fn () => (new QuickBooksConnectionValidationService(
        $quickBooks,
        $employeeList,
        app(OrganizationRealmService::class),
    ))->validateAdministratorConnection($admin, $token))
        ->toThrow(QuickBooksOAuthException::class);
});

it('rejects oauth when another organization already claimed the realm', function () {
    $existingOrganization = Organization::factory()->withRealm('222')->create();
    User::factory()->admin()->create(['organization_id' => $existingOrganization->id]);

    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => '222']);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('disconnect')->once()->with(Mockery::on(fn ($arg) => $arg->is($admin)));

    $employeeList = Mockery::mock(QboEmployeeListService::class);
    $employeeList->shouldReceive('assertCanListEmployees')->never();

    expect(fn () => (new QuickBooksConnectionValidationService(
        $quickBooks,
        $employeeList,
        app(OrganizationRealmService::class),
    ))->validateAdministratorConnection($admin, $token))
        ->toThrow(QuickBooksOAuthException::class);
});

it('disconnects and rejects oauth when employee listing fails', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => '111']);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('disconnect')->once()->with(Mockery::on(fn ($arg) => $arg->is($admin)));

    $employeeList = Mockery::mock(QboEmployeeListService::class);
    $employeeList->shouldReceive('assertCanListEmployees')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->is($token)))
        ->andThrow(new HttpResponseException(response()->json(['message' => 'fail'], 422)));

    expect(fn () => (new QuickBooksConnectionValidationService(
        $quickBooks,
        $employeeList,
        app(OrganizationRealmService::class),
    ))->validateAdministratorConnection($admin, $token))
        ->toThrow(QuickBooksOAuthException::class);
});

it('rethrows unexpected oauth validation failures', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => '111']);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('disconnect')->never();

    $employeeList = Mockery::mock(QboEmployeeListService::class);
    $employeeList->shouldReceive('assertCanListEmployees')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->is($token)))
        ->andThrow(new RuntimeException('permission denied'));

    expect(fn () => (new QuickBooksConnectionValidationService(
        $quickBooks,
        $employeeList,
        app(OrganizationRealmService::class),
    ))->validateAdministratorConnection($admin, $token))
        ->toThrow(RuntimeException::class);
});
