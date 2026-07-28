<?php

use App\Exceptions\QuickBooksOAuthException;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboEmployeeListService;
use App\Services\QuickBooksConnectionValidationService;
use App\Services\QuickBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(QuickBooksConnectionValidationService::class);

uses(RefreshDatabase::class);

it('rejects oauth when another administrator already connected a different realm', function () {
    $existingAdmin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($existingAdmin)->create(['realm_id' => '111']);

    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => '222']);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('disconnect')->once()->with(Mockery::on(fn ($arg) => $arg->is($admin)));

    $employeeList = Mockery::mock(QboEmployeeListService::class);
    $employeeList->shouldReceive('assertCanListEmployees')->never();

    expect(fn () => (new QuickBooksConnectionValidationService($quickBooks, $employeeList))
        ->validateAdministratorConnection($admin, $token))
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

    expect(fn () => (new QuickBooksConnectionValidationService($quickBooks, $employeeList))
        ->validateAdministratorConnection($admin, $token))
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

    expect(fn () => (new QuickBooksConnectionValidationService($quickBooks, $employeeList))
        ->validateAdministratorConnection($admin, $token))
        ->toThrow(RuntimeException::class);
});
