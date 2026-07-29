<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboCustomerListService;
use App\Services\QboEmployeeAuthorizationService;
use App\Services\QboListCacheService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use QuickBooksOnline\API\DataService\DataService;

uses(RefreshDatabase::class);

covers(QboCustomerListService::class);

/**
 * @return QboCustomerListService
 */
function makeQboCustomerListService(QuickBooksService $quickBooks): QboCustomerListService
{
    return new QboCustomerListService(
        $quickBooks,
        new QboListCacheService,
        new QuickBooksApiErrorFormatterService,
        new QboEmployeeAuthorizationService,
    );
}

it('returns assigned customers for a timesheet user from stored assignments', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $user->qboCustomers()->create([
        'qbo_customer_ref' => '11',
        'qbo_customer_name' => 'Acme Corp',
    ]);
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);

    $service = makeQboCustomerListService(Mockery::mock(QuickBooksService::class));

    expect($service->listForUser($user, $token))->toBe([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ])->and($service->listForUser($user, $token, true))->toBe([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);
});

it('returns an empty customer list when no assignments exist', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);

    expect(makeQboCustomerListService(Mockery::mock(QuickBooksService::class))->listForUser($user, $token))->toBe([]);
});

it('returns all active customers when all-customers access is enabled', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_all_customers_access' => true,
    ]);
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern('/FROM Customer WHERE Active = true AND Job = false/'))
        ->andReturn([
            (object) ['Id' => '11', 'DisplayName' => 'Acme Corp'],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    expect(makeQboCustomerListService($quickBooks)->listForUser($user, $token))->toBe([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);
});

it('bypasses the cache when refresh is requested for all-customers access', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 15]);

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_all_customers_access' => true,
    ]);
    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')->twice()->andReturn([]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->twice()->with($token)->andReturn($dataService);

    $service = makeQboCustomerListService($quickBooks);

    $service->listForUser($user, $token);
    $service->listForUser($user, $token, true);
});

it('lists all active customers for administrators', function () {
    config(['quickbooks.list_cache_ttl_minutes' => 0]);

    $token = QuickBooksToken::factory()->make(['realm_id' => 'realm-42']);
    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Query')
        ->once()
        ->with(Mockery::pattern('/FROM Customer WHERE Active = true AND Job = false/'))
        ->andReturn([
            (object) ['Id' => '11', 'DisplayName' => 'Beta'],
            (object) ['Id' => '12', 'DisplayName' => 'Alpha'],
        ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    expect(makeQboCustomerListService($quickBooks)->listAllActive($token))->toBe([
        ['id' => '12', 'display_name' => 'Alpha'],
        ['id' => '11', 'display_name' => 'Beta'],
    ]);
});
