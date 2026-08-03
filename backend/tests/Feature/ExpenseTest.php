<?php

use App\Http\Controllers\Api\ExpenseApprovalController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ExpensePickerController;
use App\Http\Requests\ListExpenseRequest;
use App\Http\Requests\RejectExpenseRequest;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\ExpenseApprovalService;
use App\Services\ExpenseAuthorizationService;
use App\Services\ExpenseListService;
use App\Services\ExpensePickerValidationService;
use App\Services\ExpensePresentationService;
use App\Services\ExpenseService;
use App\Services\PurchaseService;
use App\Services\QuickBooksService;
use App\Support\ExpenseApiResponse;
use Illuminate\Testing\TestResponse;
use QuickBooksOnline\API\DataService\DataService;

covers(ExpenseController::class);
covers(ExpenseApprovalController::class);
covers(ExpensePickerController::class);
covers(ExpenseListService::class);
covers(ExpenseService::class);
covers(ExpenseApprovalService::class);
covers(ExpenseAuthorizationService::class);
covers(ExpensePickerValidationService::class);
covers(ExpensePresentationService::class);
covers(PurchaseService::class);
covers(ExpenseApiResponse::class);
covers(StoreExpenseRequest::class);
covers(UpdateExpenseRequest::class);
covers(ListExpenseRequest::class);
covers(RejectExpenseRequest::class);

/**
 * Asserts expense resource fields returned by the API.
 *
 * @param  TestResponse  $response
 * @param  array<string, mixed>  $expected
 */
function assertExpenseResourceFields($response, array $expected): void
{
    foreach ($expected as $field => $value) {
        $response->assertJsonPath("data.{$field}", $value);
    }
}

it('requires authentication for expenses', function () {
    $this->getJson('/api/expenses')->assertUnauthorized();
});

describe('employee expenses', function () {
    beforeEach(function () {
        mockQboExpensePickers();
        $user = actingAsWithQboEmployee();
        QuickBooksToken::factory()->forUser(
            User::factory()->admin()->create(['organization_id' => $user->organization_id])
        )->create(['realm_id' => 'realm-expense']);
    });

    it('creates a pending expense', function () {
        $user = auth()->user();

        $response = $this->postJson('/api/expenses', [
            'amount' => 42.5,
            'txn_date' => '2026-08-03',
            'payment_type' => 'Cash',
            'payment_account_ref' => '35',
            'expense_account_ref' => '7',
            'description' => 'AI tooling credits',
        ], frontendHeaders());

        $response->assertCreated();

        assertExpenseResourceFields($response, [
            'user_id' => $user->id,
            'employee_name' => $user->name,
            'amount' => '42.50',
            'txn_date' => '2026-08-03',
            'payment_type' => 'Cash',
            'payment_account_ref' => '35',
            'payment_account_name' => 'Checking',
            'expense_account_ref' => '7',
            'expense_account_name' => 'Office Expenses',
            'vendor_ref' => null,
            'vendor_name' => null,
            'customer_ref' => null,
            'customer_name' => null,
            'project_ref' => null,
            'project_name' => null,
            'description' => 'AI tooling credits',
            'is_billable' => false,
            'status' => 'pending',
            'reviewed_by_id' => null,
            'reviewed_by_name' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'qbo_id' => null,
        ]);

        expect($response->json('data.id'))->toBeInt()
            ->and($response->json('data.created_at'))->not->toBeNull();
    });

    it('lists expenses for the signed-in employee', function () {
        $user = auth()->user();
        $expense = Expense::factory()->forUser($user)->create([
            'amount' => 25.00,
            'txn_date' => '2026-08-02',
            'description' => 'Listed expense',
            'payment_account_ref' => '35',
            'expense_account_ref' => '7',
        ]);

        $response = $this->getJson('/api/expenses', frontendHeaders())->assertOk();

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'user_id',
                        'employee_name',
                        'amount',
                        'txn_date',
                        'payment_type',
                        'payment_account_ref',
                        'payment_account_name',
                        'expense_account_ref',
                        'expense_account_name',
                        'vendor_ref',
                        'vendor_name',
                        'customer_ref',
                        'customer_name',
                        'project_ref',
                        'project_name',
                        'description',
                        'is_billable',
                        'status',
                        'reviewed_by_id',
                        'reviewed_by_name',
                        'reviewed_at',
                        'rejection_reason',
                        'qbo_id',
                        'created_at',
                    ],
                ],
            ]);

        expect($response->json('data.0.id'))->toBe($expense->id)
            ->and($response->json('data.0.user_id'))->toBe($user->id)
            ->and($response->json('data.0.employee_name'))->toBe($user->name)
            ->and($response->json('data.0.amount'))->toBe('25.00')
            ->and($response->json('data.0.txn_date'))->toBe('2026-08-02')
            ->and($response->json('data.0.payment_account_name'))->toBe('Checking')
            ->and($response->json('data.0.expense_account_name'))->toBe('Office Expenses')
            ->and($response->json('data.0.description'))->toBe('Listed expense')
            ->and($response->json('data.0.status'))->toBe('pending')
            ->and($response->json('data.0.is_billable'))->toBeFalse()
            ->and($response->json('data.0.qbo_id'))->toBeNull();
    });

    it('updates a pending expense', function () {
        $user = auth()->user();
        $expense = Expense::factory()->forUser($user)->create();

        $response = $this->patchJson("/api/expenses/{$expense->id}", [
            'description' => 'Updated notes',
            'is_billable' => true,
        ], frontendHeaders())->assertOk();

        assertExpenseResourceFields($response, [
            'id' => $expense->id,
            'user_id' => $user->id,
            'employee_name' => $user->name,
            'amount' => (string) $expense->amount,
            'txn_date' => $expense->txn_date?->toDateString(),
            'payment_type' => $expense->payment_type->value,
            'payment_account_ref' => $expense->payment_account_ref,
            'payment_account_name' => 'Checking',
            'expense_account_ref' => $expense->expense_account_ref,
            'expense_account_name' => 'Office Expenses',
            'vendor_ref' => $expense->vendor_ref,
            'vendor_name' => null,
            'customer_ref' => $expense->customer_ref,
            'customer_name' => null,
            'project_ref' => $expense->project_ref,
            'project_name' => null,
            'description' => 'Updated notes',
            'is_billable' => true,
            'status' => 'pending',
            'reviewed_by_id' => null,
            'reviewed_by_name' => null,
            'reviewed_at' => null,
            'rejection_reason' => null,
            'qbo_id' => null,
        ]);
    });

    it('rejects updates to approved expenses', function () {
        $user = auth()->user();
        $expense = Expense::factory()->forUser($user)->approved()->create();

        $this->patchJson("/api/expenses/{$expense->id}", [
            'description' => 'Too late',
        ], frontendHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('error', 'expense_not_editable');
    });

    it('deletes a pending expense', function () {
        $user = auth()->user();
        $expense = Expense::factory()->forUser($user)->create();

        $this->deleteJson("/api/expenses/{$expense->id}", [], frontendHeaders())
            ->assertNoContent();

        expect(Expense::query()->whereKey($expense->id)->exists())->toBeFalse();
    });
});

describe('expense approvals', function () {
    beforeEach(function () {
        mockQboExpensePickers();
    });

    it('lets an administrator approve a pending expense and sync to quickbooks', function () {
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-expense']);
        $employee = timesheetUserFor($admin);
        $expense = Expense::factory()->forUser($employee)->create([
            'amount' => 12.34,
            'description' => 'Ready for approval',
        ]);

        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '501']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $response = $this->postJson("/api/admin/expense-approvals/{$expense->id}/approve", [], frontendHeaders())
            ->assertOk();

        assertExpenseResourceFields($response, [
            'user_id' => $employee->id,
            'employee_name' => $employee->name,
            'amount' => '12.34',
            'txn_date' => $expense->txn_date?->toDateString(),
            'payment_type' => $expense->payment_type->value,
            'payment_account_ref' => $expense->payment_account_ref,
            'payment_account_name' => 'Checking',
            'expense_account_ref' => $expense->expense_account_ref,
            'expense_account_name' => 'Office Expenses',
            'vendor_ref' => $expense->vendor_ref,
            'vendor_name' => null,
            'customer_ref' => $expense->customer_ref,
            'customer_name' => null,
            'project_ref' => $expense->project_ref,
            'project_name' => null,
            'description' => 'Ready for approval',
            'is_billable' => false,
            'status' => 'approved',
            'reviewed_by_id' => $admin->id,
            'reviewed_by_name' => $admin->name,
            'rejection_reason' => null,
            'qbo_id' => '501',
        ]);

        expect($response->json('data.reviewed_at'))->not->toBeNull()
            ->and($response->json('data.id'))->toBe($expense->id);
    });

    it('lets a supervisor approve direct report expenses', function () {
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-expense']);
        $supervisor = timesheetUserFor($admin, ['qbo_employee_ref' => '9']);
        $employee = timesheetUserFor($admin, ['supervisor_id' => $supervisor->id]);
        $expense = Expense::factory()->forUser($employee)->create();

        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '502']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->actingAs($supervisor)
            ->postJson("/api/expense-approvals/{$expense->id}/approve", [], frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    });

    it('rejects approval from unrelated employees', function () {
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-expense']);
        $employee = timesheetUserFor($admin);
        $otherEmployee = timesheetUserFor($admin, ['qbo_employee_ref' => '9']);
        $expense = Expense::factory()->forUser($employee)->create();

        $this->actingAs($otherEmployee)
            ->postJson("/api/expense-approvals/{$expense->id}/approve", [], frontendHeaders())
            ->assertForbidden()
            ->assertJsonPath('error', 'expense_review_forbidden');
    });

    it('rejects a pending expense without syncing to quickbooks', function () {
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-expense']);
        $employee = timesheetUserFor($admin);
        $expense = Expense::factory()->forUser($employee)->create();

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/expense-approvals/{$expense->id}/reject", [
                'reason' => 'Wrong category',
            ], frontendHeaders())
            ->assertOk();

        assertExpenseResourceFields($response, [
            'user_id' => $employee->id,
            'employee_name' => $employee->name,
            'amount' => (string) $expense->amount,
            'txn_date' => $expense->txn_date?->toDateString(),
            'payment_type' => $expense->payment_type->value,
            'payment_account_ref' => $expense->payment_account_ref,
            'payment_account_name' => 'Checking',
            'expense_account_ref' => $expense->expense_account_ref,
            'expense_account_name' => 'Office Expenses',
            'vendor_ref' => $expense->vendor_ref,
            'vendor_name' => null,
            'customer_ref' => $expense->customer_ref,
            'customer_name' => null,
            'project_ref' => $expense->project_ref,
            'project_name' => null,
            'description' => $expense->description,
            'is_billable' => false,
            'status' => 'rejected',
            'reviewed_by_id' => $admin->id,
            'reviewed_by_name' => $admin->name,
            'rejection_reason' => 'Wrong category',
            'qbo_id' => null,
        ]);

        expect($response->json('data.reviewed_at'))->not->toBeNull()
            ->and($response->json('data.id'))->toBe($expense->id);
    });

    it('lists pending expenses for administrators', function () {
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-expense']);
        $employee = timesheetUserFor($admin);
        Expense::factory()->forUser($employee)->create(['description' => 'Needs review']);
        Expense::factory()->forUser($employee)->approved()->create();

        $response = $this->getJson('/api/admin/expense-approvals', frontendHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'user_id',
                        'employee_name',
                        'amount',
                        'txn_date',
                        'payment_type',
                        'payment_account_ref',
                        'payment_account_name',
                        'expense_account_ref',
                        'expense_account_name',
                        'vendor_ref',
                        'vendor_name',
                        'customer_ref',
                        'customer_name',
                        'project_ref',
                        'project_name',
                        'description',
                        'is_billable',
                        'status',
                        'reviewed_by_id',
                        'reviewed_by_name',
                        'reviewed_at',
                        'rejection_reason',
                        'qbo_id',
                        'created_at',
                    ],
                ],
            ]);

        expect($response->json('data.0.description'))->toBe('Needs review')
            ->and($response->json('data.0.status'))->toBe('pending')
            ->and($response->json('data.0.employee_name'))->toBe($employee->name)
            ->and($response->json('data.0.payment_account_name'))->toBe('Checking')
            ->and($response->json('data.0.expense_account_name'))->toBe('Office Expenses');
    });
});

describe('expense pickers', function () {
    it('lists expense accounts payment accounts and vendors', function () {
        mockQboExpensePickers();
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-expense']);

        $this->getJson('/api/quickbooks/expense-accounts', frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', '7');

        $this->getJson('/api/quickbooks/payment-accounts', frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', '35');

        $this->getJson('/api/quickbooks/vendors', frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.id', '56');
    });

    it('caches account lists until refresh is requested', function () {
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-cache']);

        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Query')->twice()->andReturn([
            (object) ['Id' => '7', 'Name' => 'Office', 'AccountType' => 'Expense'],
            (object) ['Id' => '35', 'Name' => 'Checking', 'AccountType' => 'Bank'],
        ]);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->getJson('/api/quickbooks/expense-accounts', frontendHeaders())->assertOk();
        $this->getJson('/api/quickbooks/expense-accounts', frontendHeaders())->assertOk();
        $this->getJson('/api/quickbooks/expense-accounts?refresh=1', frontendHeaders())->assertOk();
    });
});

describe('expense approval security', function () {
    it('forbids administrators from approving expenses in another organization', function () {
        mockQboExpensePickers();
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-home']);

        $foreignOrganization = Organization::factory()->create();
        $foreignEmployee = User::factory()->create([
            'organization_id' => $foreignOrganization->id,
            'qbo_employee_ref' => '88',
        ]);
        $expense = Expense::factory()->forUser($foreignEmployee)->create();

        $this->postJson("/api/admin/expense-approvals/{$expense->id}/approve", [], frontendHeaders())
            ->assertNotFound();
    });
});
