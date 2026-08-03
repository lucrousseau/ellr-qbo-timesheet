<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use QuickBooksOnline\API\DataService\DataService;

uses(RefreshDatabase::class);

covers(PurchaseService::class);

it('creates a QuickBooks Purchase expense with account-based line detail', function () {
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->forUser($user)->make(['realm_id' => 'realm-1']);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')
        ->once()
        ->andReturn((object) ['Id' => '901']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);
    $apiErrors = Mockery::mock(QuickBooksApiErrorFormatterService::class);

    $service = new PurchaseService($quickBooks, $apiErrors);
    $result = $service->createForUser($user, $token, [
        'amount' => 25.5,
        'txn_date' => '2026-08-03',
        'payment_type' => 'Cash',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'vendor_ref' => '56',
        'customer_ref' => '11',
        'description' => 'AI credits',
        'is_billable' => false,
    ]);

    expect($result->Id)->toBe('901');
});
