<?php

use App\Models\Expense;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\ExpensePresentationService;
use App\Services\QboAccountListService;
use App\Services\QboCustomerListService;
use App\Services\QboProjectListService;
use App\Services\QboVendorListService;
use App\Services\QuickBooksTokenResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(ExpensePresentationService::class);

uses(RefreshDatabase::class);

it('maps one expense with resolved labels when a token is available', function () {
    $viewer = User::factory()->create();
    $expense = Expense::factory()->forUser($viewer)->create([
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'vendor_ref' => '56',
        'customer_ref' => '11',
        'description' => 'Billable expense',
    ]);
    $token = QuickBooksToken::factory()->forUser($viewer)->create();

    $accounts = Mockery::mock(QboAccountListService::class);
    $accounts->shouldReceive('listAll')->once()->with($token)->andReturn([
        ['id' => '35', 'display_name' => 'Checking'],
        ['id' => '7', 'display_name' => 'Office Expenses'],
    ]);
    $vendors = Mockery::mock(QboVendorListService::class);
    $vendors->shouldReceive('listActive')->once()->with($token)->andReturn([
        ['id' => '56', 'display_name' => 'Office Depot'],
    ]);
    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listForUser')->once()->with($expense->user, $token)->andReturn([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);
    $projects = Mockery::mock(QboProjectListService::class);
    $projects->shouldNotReceive('listForCustomer');

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($viewer)->andReturn($token);

    $service = new ExpensePresentationService($accounts, $vendors, $customers, $projects, $tokenResolver);
    $payload = $service->resource($expense, $viewer);

    expect($payload['payment_account_name'])->toBe('Checking')
        ->and($payload['expense_account_name'])->toBe('Office Expenses')
        ->and($payload['vendor_name'])->toBe('Office Depot')
        ->and($payload['customer_name'])->toBe('Acme Corp')
        ->and($payload['project_name'])->toBeNull()
        ->and($payload['description'])->toBe('Billable expense');
});

it('uses a pre-resolved token without calling the token resolver again', function () {
    $viewer = User::factory()->create();
    $expense = Expense::factory()->forUser($viewer)->create([
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
    ]);
    $token = QuickBooksToken::factory()->forUser($viewer)->create();

    $accounts = Mockery::mock(QboAccountListService::class);
    $accounts->shouldReceive('listAll')->once()->with($token)->andReturn([
        ['id' => '35', 'display_name' => 'Checking'],
        ['id' => '7', 'display_name' => 'Office Expenses'],
    ]);
    $vendors = Mockery::mock(QboVendorListService::class);
    $vendors->shouldReceive('listActive')->once()->with($token)->andReturn([]);
    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldNotReceive('listForUser');
    $projects = Mockery::mock(QboProjectListService::class);
    $projects->shouldNotReceive('listForCustomer');

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldNotReceive('resolve');

    $service = new ExpensePresentationService($accounts, $vendors, $customers, $projects, $tokenResolver);
    $payload = $service->resource($expense, $viewer, $token);

    expect($payload['payment_account_name'])->toBe('Checking')
        ->and($payload['expense_account_name'])->toBe('Office Expenses');
});

it('returns null labels when quickbooks is disconnected', function () {
    $viewer = User::factory()->create();
    $expense = Expense::factory()->forUser($viewer)->create([
        'payment_account_ref' => '35',
    ]);

    $accounts = Mockery::mock(QboAccountListService::class);
    $accounts->shouldNotReceive('listAll');
    $vendors = Mockery::mock(QboVendorListService::class);
    $vendors->shouldNotReceive('listActive');
    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldNotReceive('listForUser');
    $projects = Mockery::mock(QboProjectListService::class);
    $projects->shouldNotReceive('listForCustomer');

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')
        ->once()
        ->with($viewer)
        ->andThrow(new HttpResponseException(response()->json(['message' => 'not connected'], 403)));

    $service = new ExpensePresentationService($accounts, $vendors, $customers, $projects, $tokenResolver);
    $payload = $service->resource($expense, $viewer);

    expect($payload['payment_account_ref'])->toBe('35')
        ->and($payload['payment_account_name'])->toBeNull()
        ->and($payload['expense_account_name'])->toBeNull();
});

it('resolves project labels when both customer and project refs are present', function () {
    $viewer = User::factory()->create();
    $expense = Expense::factory()->forUser($viewer)->create([
        'customer_ref' => '11',
        'project_ref' => '22',
    ]);
    $token = QuickBooksToken::factory()->forUser($viewer)->create();

    $accounts = Mockery::mock(QboAccountListService::class);
    $accounts->shouldReceive('listAll')->once()->andReturn([]);
    $vendors = Mockery::mock(QboVendorListService::class);
    $vendors->shouldReceive('listActive')->once()->andReturn([]);
    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listForUser')->once()->andReturn([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);
    $projects = Mockery::mock(QboProjectListService::class);
    $projects->shouldReceive('listForCustomer')->once()->with($token, '11')->andReturn([
        ['id' => '22', 'display_name' => 'Website redesign'],
    ]);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldNotReceive('resolve');

    $service = new ExpensePresentationService($accounts, $vendors, $customers, $projects, $tokenResolver);
    $payload = $service->resource($expense, $viewer, $token);

    expect($payload['customer_name'])->toBe('Acme Corp')
        ->and($payload['project_name'])->toBe('Website redesign');
});

it('maps a collection with one shared token lookup', function () {
    $viewer = User::factory()->create();
    $expenses = Expense::factory()->count(2)->forUser($viewer)->create([
        'payment_account_ref' => '35',
    ]);
    $token = QuickBooksToken::factory()->forUser($viewer)->create();

    $accounts = Mockery::mock(QboAccountListService::class);
    $accounts->shouldReceive('listAll')->twice()->andReturn([
        ['id' => '35', 'display_name' => 'Checking'],
    ]);
    $vendors = Mockery::mock(QboVendorListService::class);
    $vendors->shouldReceive('listActive')->twice()->andReturn([]);
    $customers = Mockery::mock(QboCustomerListService::class);
    $projects = Mockery::mock(QboProjectListService::class);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldNotReceive('resolve');

    $service = new ExpensePresentationService($accounts, $vendors, $customers, $projects, $tokenResolver);
    $rows = $service->collection($expenses, $viewer, $token);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['payment_account_name'])->toBe('Checking')
        ->and($rows[1]['payment_account_name'])->toBe('Checking');
});

it('resolves one token for a collection when none is pre-resolved', function () {
    $viewer = User::factory()->create();
    $expenses = Expense::factory()->count(2)->forUser($viewer)->create([
        'payment_account_ref' => '35',
    ]);
    $token = QuickBooksToken::factory()->forUser($viewer)->create();

    $accounts = Mockery::mock(QboAccountListService::class);
    $accounts->shouldReceive('listAll')->twice()->andReturn([
        ['id' => '35', 'display_name' => 'Checking'],
    ]);
    $vendors = Mockery::mock(QboVendorListService::class);
    $vendors->shouldReceive('listActive')->twice()->andReturn([]);
    $customers = Mockery::mock(QboCustomerListService::class);
    $projects = Mockery::mock(QboProjectListService::class);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($viewer)->andReturn($token);

    $service = new ExpensePresentationService($accounts, $vendors, $customers, $projects, $tokenResolver);
    $rows = $service->collection($expenses, $viewer);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['payment_account_name'])->toBe('Checking');
});

it('returns null labels for a collection when quickbooks is disconnected', function () {
    $viewer = User::factory()->create();
    $expenses = Expense::factory()->count(2)->forUser($viewer)->create();

    $accounts = Mockery::mock(QboAccountListService::class);
    $accounts->shouldNotReceive('listAll');
    $vendors = Mockery::mock(QboVendorListService::class);
    $customers = Mockery::mock(QboCustomerListService::class);
    $projects = Mockery::mock(QboProjectListService::class);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')
        ->times(3)
        ->with($viewer)
        ->andThrow(new HttpResponseException(response()->json(['message' => 'not connected'], 403)));

    $service = new ExpensePresentationService($accounts, $vendors, $customers, $projects, $tokenResolver);
    $rows = $service->collection($expenses, $viewer);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['payment_account_name'])->toBeNull()
        ->and($rows[1]['payment_account_name'])->toBeNull();
});

it('returns null labels when the expense owner is missing', function () {
    $viewer = User::factory()->create();
    $expense = Expense::factory()->make([
        'user_id' => null,
        'organization_id' => null,
    ]);

    $accounts = Mockery::mock(QboAccountListService::class);
    $accounts->shouldNotReceive('listAll');
    $vendors = Mockery::mock(QboVendorListService::class);
    $customers = Mockery::mock(QboCustomerListService::class);
    $projects = Mockery::mock(QboProjectListService::class);
    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldNotReceive('resolve');

    $service = new ExpensePresentationService($accounts, $vendors, $customers, $projects, $tokenResolver);
    $payload = $service->resource($expense, $viewer);

    expect($payload['payment_account_name'])->toBeNull()
        ->and($payload['expense_account_name'])->toBeNull();
});
