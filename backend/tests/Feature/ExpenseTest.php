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
        $this->postJson('/api/expenses', [
            'amount' => 42.5,
            'txn_date' => '2026-08-03',
            'payment_type' => 'Cash',
            'payment_account_ref' => '35',
            'expense_account_ref' => '7',
            'description' => 'AI tooling credits',
        ], frontendHeaders())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', '42.50')
            ->assertJsonPath('data.description', 'AI tooling credits');
    });

    it('lists expenses for the signed-in employee', function () {
        $user = auth()->user();
        Expense::factory()->forUser($user)->create([
            'description' => 'Listed expense',
        ]);

        $this->getJson('/api/expenses', frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.description', 'Listed expense')
            ->assertJsonPath('data.0.status', 'pending');
    });

    it('updates a pending expense', function () {
        $user = auth()->user();
        $expense = Expense::factory()->forUser($user)->create();

        $this->patchJson("/api/expenses/{$expense->id}", [
            'description' => 'Updated notes',
        ], frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated notes');
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

        $this->postJson("/api/admin/expense-approvals/{$expense->id}/approve", [], frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.qbo_id', '501');
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

        $this->actingAs($admin)
            ->postJson("/api/admin/expense-approvals/{$expense->id}/reject", [
                'reason' => 'Wrong category',
            ], frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Wrong category')
            ->assertJsonPath('data.qbo_id', null);
    });

    it('lists pending expenses for administrators', function () {
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-expense']);
        $employee = timesheetUserFor($admin);
        Expense::factory()->forUser($employee)->create(['description' => 'Needs review']);
        Expense::factory()->forUser($employee)->approved()->create();

        $this->getJson('/api/admin/expense-approvals', frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.description', 'Needs review')
            ->assertJsonCount(1, 'data');
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
