<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\QuickBooksApiErrorFormatterService;
use App\Services\QuickBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use QuickBooksOnline\API\DataService\DataService;
use Tests\Support\MockeryCapture;

uses(RefreshDatabase::class);

covers(PurchaseService::class);

it('creates a QuickBooks Purchase expense with account-based line detail', function () {
    $captured = null;
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $token = QuickBooksToken::factory()->forUser($user)->make(['realm_id' => 'realm-1']);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')
        ->once()
        ->with(Mockery::capture($captured))
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

    $purchase = MockeryCapture::unwrap($captured);

    expect($result->Id)->toBe('901')
        ->and($purchase->PaymentType->value)->toBe('Cash')
        ->and((string) $purchase->AccountRef->value)->toBe('35')
        ->and($purchase->TxnDate)->toBe('2026-08-03')
        ->and((string) $purchase->EntityRef->value)->toBe('56')
        ->and($purchase->Line[0]->Amount)->toBe(25.5)
        ->and($purchase->Line[0]->DetailType->value)->toBe('AccountBasedExpenseLineDetail')
        ->and((string) $purchase->Line[0]->AccountBasedExpenseLineDetail->AccountRef->value)->toBe('7')
        ->and($purchase->Line[0]->AccountBasedExpenseLineDetail->BillableStatus->value)->toBe('NotBillable')
        ->and((string) $purchase->Line[0]->AccountBasedExpenseLineDetail->CustomerRef->value)->toBe('11')
        ->and($purchase->Line[0]->Description)->toBe('AI credits');
});

it('maps billable expenses to quickbooks billable status values', function () {
    $captured = null;
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->make();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')
        ->once()
        ->with(Mockery::capture($captured))
        ->andReturn((object) ['Id' => '902']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);
    $apiErrors = Mockery::mock(QuickBooksApiErrorFormatterService::class);

    (new PurchaseService($quickBooks, $apiErrors))->createForUser($user, $token, [
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_type' => 'CreditCard',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'is_billable' => true,
    ]);

    expect(MockeryCapture::unwrap($captured)->Line[0]->AccountBasedExpenseLineDetail->BillableStatus->value)->toBe('Billable');
});

it('prefers project ref over customer ref for line customer mapping', function () {
    $captured = null;
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->make();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')
        ->once()
        ->with(Mockery::capture($captured))
        ->andReturn((object) ['Id' => '903']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);
    $apiErrors = Mockery::mock(QuickBooksApiErrorFormatterService::class);

    (new PurchaseService($quickBooks, $apiErrors))->createForUser($user, $token, [
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_type' => 'Cash',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'customer_ref' => '11',
        'project_ref' => '22',
        'is_billable' => false,
    ]);

    expect((string) MockeryCapture::unwrap($captured)->Line[0]->AccountBasedExpenseLineDetail->CustomerRef->value)
        ->toBe('22');
});

it('omits vendor entity ref and customer ref when not provided', function () {
    $captured = null;
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->make();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')
        ->once()
        ->with(Mockery::capture($captured))
        ->andReturn((object) ['Id' => '904']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);
    $apiErrors = Mockery::mock(QuickBooksApiErrorFormatterService::class);

    (new PurchaseService($quickBooks, $apiErrors))->createForUser($user, $token, [
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_type' => 'Check',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'is_billable' => false,
    ]);

    $purchase = MockeryCapture::unwrap($captured);

    expect(isset($purchase->EntityRef))->toBeFalse()
        ->and(isset($purchase->Line[0]->AccountBasedExpenseLineDetail->CustomerRef))->toBeFalse()
        ->and(isset($purchase->Line[0]->Description))->toBeFalse()
        ->and($purchase->PaymentType)->toBe('Check');
});

it('omits line description when the payload description is null', function () {
    $captured = null;
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->make();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')
        ->once()
        ->with(Mockery::capture($captured))
        ->andReturn((object) ['Id' => '905']);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);
    $apiErrors = Mockery::mock(QuickBooksApiErrorFormatterService::class);

    (new PurchaseService($quickBooks, $apiErrors))->createForUser($user, $token, [
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'description' => null,
        'is_billable' => false,
    ]);

    expect(isset(MockeryCapture::unwrap($captured)->Line[0]->Description))->toBeFalse();
});

it('aborts when quickbooks returns an api error', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->make();
    $apiError = (object) ['responseBody' => '{"Fault":{"Error":[{"Message":"Bad request"}]}}'];

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn(null);
    $dataService->shouldReceive('getLastError')->once()->andReturn($apiError);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);

    $apiErrors = Mockery::mock(QuickBooksApiErrorFormatterService::class);
    $apiErrors->shouldReceive('jsonResponse')
        ->once()
        ->with($apiError)
        ->andReturn(response()->json(['message' => 'QBO error'], 422));

    expect(fn () => (new PurchaseService($quickBooks, $apiErrors))->createForUser($user, $token, [
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'is_billable' => false,
    ]))->toThrow(HttpResponseException::class);
});
