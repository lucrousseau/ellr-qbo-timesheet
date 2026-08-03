<?php

use App\Enums\ExpensePaymentType;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\ExpensePickerValidationService;
use App\Services\ExpenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(ExpenseService::class);

uses(RefreshDatabase::class);

it('persists all optional create fields from validated input', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    $this->mock(ExpensePickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidSelections')->once();
    });

    $expense = app(ExpenseService::class)->createForUser($employee, [
        'amount' => 42.5,
        'txn_date' => '2026-08-03',
        'payment_type' => 'CreditCard',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'vendor_ref' => '56',
        'customer_ref' => '11',
        'project_ref' => '22',
        'description' => 'AI tooling credits',
        'is_billable' => true,
    ]);

    expect($expense->organization_id)->toBe($employee->organization_id)
        ->and($expense->user_id)->toBe($employee->id)
        ->and($expense->status)->toBe(ExpenseStatus::Pending)
        ->and((string) $expense->amount)->toBe('42.50')
        ->and($expense->txn_date?->toDateString())->toBe('2026-08-03')
        ->and($expense->payment_type)->toBe(ExpensePaymentType::CreditCard)
        ->and($expense->payment_account_ref)->toBe('35')
        ->and($expense->expense_account_ref)->toBe('7')
        ->and($expense->vendor_ref)->toBe('56')
        ->and($expense->customer_ref)->toBe('11')
        ->and($expense->project_ref)->toBe('22')
        ->and($expense->description)->toBe('AI tooling credits')
        ->and($expense->is_billable)->toBeTrue();
});

it('defaults payment type to cash and billable to false when omitted', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);

    $this->mock(ExpensePickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidSelections')->once();
    });

    $expense = app(ExpenseService::class)->createForUser($employee, [
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
    ]);

    expect($expense->payment_type)->toBe(ExpensePaymentType::Cash)
        ->and($expense->is_billable)->toBeFalse()
        ->and($expense->vendor_ref)->toBeNull()
        ->and($expense->customer_ref)->toBeNull()
        ->and($expense->project_ref)->toBeNull()
        ->and($expense->description)->toBeNull();
});

it('updates a pending expense owned by the employee', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);
    $expense = Expense::factory()->forUser($employee)->create();

    $this->mock(ExpensePickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidExpense')->once();
    });

    $updated = app(ExpenseService::class)->updateForUser($employee, $expense->id, [
        'description' => 'Updated notes',
        'is_billable' => true,
        'amount' => 99.99,
    ]);

    expect($updated->description)->toBe('Updated notes')
        ->and($updated->is_billable)->toBeTrue()
        ->and((string) $updated->amount)->toBe('99.99');
});

it('updates all mutable expense fields', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);
    $expense = Expense::factory()->forUser($employee)->create();

    $this->mock(ExpensePickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidExpense')->once();
    });

    $updated = app(ExpenseService::class)->updateForUser($employee, $expense->id, [
        'amount' => 50,
        'txn_date' => '2026-08-04',
        'payment_type' => 'Check',
        'payment_account_ref' => '36',
        'expense_account_ref' => '8',
        'vendor_ref' => '57',
        'customer_ref' => '12',
        'project_ref' => '23',
        'description' => 'Full update',
        'is_billable' => false,
    ]);

    expect((string) $updated->amount)->toBe('50.00')
        ->and($updated->txn_date?->toDateString())->toBe('2026-08-04')
        ->and($updated->payment_type)->toBe(ExpensePaymentType::Check)
        ->and($updated->payment_account_ref)->toBe('36')
        ->and($updated->expense_account_ref)->toBe('8')
        ->and($updated->vendor_ref)->toBe('57')
        ->and($updated->customer_ref)->toBe('12')
        ->and($updated->project_ref)->toBe('23')
        ->and($updated->description)->toBe('Full update')
        ->and($updated->is_billable)->toBeFalse();
});

it('rejects updates to approved expenses', function () {
    $employee = User::factory()->create();
    $expense = Expense::factory()->forUser($employee)->approved()->create();

    app(ExpenseService::class)->updateForUser($employee, $expense->id, [
        'description' => 'Too late',
    ]);
})->throws(HttpResponseException::class);

it('rejects updates to rejected expenses', function () {
    $employee = User::factory()->create();
    $expense = Expense::factory()->forUser($employee)->rejected()->create();

    app(ExpenseService::class)->updateForUser($employee, $expense->id, [
        'description' => 'Too late',
    ]);
})->throws(HttpResponseException::class);

it('deletes pending and rejected expenses', function () {
    $employee = User::factory()->create();
    $pending = Expense::factory()->forUser($employee)->create();
    $rejected = Expense::factory()->forUser($employee)->rejected()->create();

    $service = app(ExpenseService::class);
    $service->deleteForUser($employee, $pending->id);
    $service->deleteForUser($employee, $rejected->id);

    expect(Expense::query()->whereKey([$pending->id, $rejected->id])->exists())->toBeFalse();
});

it('rejects deleting approved expenses', function () {
    $employee = User::factory()->create();
    $expense = Expense::factory()->forUser($employee)->approved()->create();

    app(ExpenseService::class)->deleteForUser($employee, $expense->id);
})->throws(HttpResponseException::class);

it('returns not found when the expense is not owned by the employee', function () {
    $employee = User::factory()->create();
    $otherEmployee = User::factory()->create();
    $expense = Expense::factory()->forUser($otherEmployee)->create();

    app(ExpenseService::class)->findOwnedExpense($employee, $expense->id);
})->throws(HttpResponseException::class);

it('validates picker selections on create', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);

    $this->mock(ExpensePickerValidationService::class, function ($mock) use ($employee) {
        $mock->shouldReceive('assertValidSelections')
            ->once()
            ->with($employee, Mockery::type(QuickBooksToken::class), Mockery::type('array'));
    });

    app(ExpenseService::class)->createForUser($employee, [
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'customer_ref' => '11',
    ]);
});

it('revalidates picker selections on update', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);
    $expense = Expense::factory()->forUser($employee)->create([
        'customer_ref' => '11',
    ]);

    $this->mock(ExpensePickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidExpense')->once();
    });

    $updated = app(ExpenseService::class)->updateForUser($employee, $expense->id, [
        'customer_ref' => '12',
    ]);

    expect($updated->customer_ref)->toBe('12');
});
