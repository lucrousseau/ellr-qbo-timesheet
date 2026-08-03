<?php

use App\Enums\ExpensePaymentType;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\User;
use App\Support\ExpenseApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

covers(ExpenseApiResponse::class);

uses(RefreshDatabase::class);

it('maps an expense resource with every api field and resolved labels', function () {
    Carbon::setTestNow('2026-08-03 12:00:00');

    $employee = User::factory()->create(['name' => 'Jane Doe']);
    $reviewer = User::factory()->admin()->create(['name' => 'Admin User']);
    $expense = Expense::factory()->forUser($employee)->create([
        'amount' => 42.50,
        'txn_date' => '2026-08-03',
        'payment_type' => ExpensePaymentType::CreditCard,
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'vendor_ref' => '56',
        'customer_ref' => '11',
        'project_ref' => '22',
        'description' => 'AI tooling credits',
        'is_billable' => true,
        'status' => ExpenseStatus::Approved,
        'reviewed_by_id' => $reviewer->id,
        'reviewed_at' => now(),
        'rejection_reason' => null,
        'qbo_id' => '501',
    ]);

    $payload = ExpenseApiResponse::resource($expense->fresh(['user', 'reviewedBy']), [
        'payment_account_name' => 'Checking',
        'expense_account_name' => 'Office Expenses',
        'vendor_name' => 'Office Depot',
        'customer_name' => 'Acme Corp',
        'project_name' => 'Website redesign',
    ]);

    expect($payload)->toBe([
        'id' => $expense->id,
        'user_id' => $employee->id,
        'employee_name' => 'Jane Doe',
        'amount' => '42.50',
        'txn_date' => '2026-08-03',
        'payment_type' => 'CreditCard',
        'payment_account_ref' => '35',
        'payment_account_name' => 'Checking',
        'expense_account_ref' => '7',
        'expense_account_name' => 'Office Expenses',
        'vendor_ref' => '56',
        'vendor_name' => 'Office Depot',
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website redesign',
        'description' => 'AI tooling credits',
        'is_billable' => true,
        'status' => 'approved',
        'reviewed_by_id' => $reviewer->id,
        'reviewed_by_name' => 'Admin User',
        'reviewed_at' => $expense->reviewed_at?->toIso8601String(),
        'rejection_reason' => null,
        'qbo_id' => '501',
        'created_at' => $expense->created_at?->toIso8601String(),
    ]);

    Carbon::setTestNow();
});

it('defaults label fields to null when labels are omitted', function () {
    $employee = User::factory()->create(['name' => 'Jane Doe']);
    $expense = Expense::factory()->forUser($employee)->create([
        'vendor_ref' => null,
        'customer_ref' => null,
        'project_ref' => null,
        'description' => null,
    ]);

    $payload = ExpenseApiResponse::resource($expense->fresh(['user', 'reviewedBy']));

    expect($payload['payment_account_name'])->toBeNull()
        ->and($payload['expense_account_name'])->toBeNull()
        ->and($payload['vendor_name'])->toBeNull()
        ->and($payload['customer_name'])->toBeNull()
        ->and($payload['project_name'])->toBeNull()
        ->and($payload['vendor_ref'])->toBeNull()
        ->and($payload['customer_ref'])->toBeNull()
        ->and($payload['project_ref'])->toBeNull()
        ->and($payload['description'])->toBeNull()
        ->and($payload['reviewed_by_id'])->toBeNull()
        ->and($payload['reviewed_by_name'])->toBeNull()
        ->and($payload['reviewed_at'])->toBeNull()
        ->and($payload['rejection_reason'])->toBeNull()
        ->and($payload['qbo_id'])->toBeNull()
        ->and($payload['status'])->toBe('pending')
        ->and($payload['is_billable'])->toBeFalse();
});

it('maps rejected expenses with rejection metadata', function () {
    $employee = User::factory()->create(['name' => 'Jane Doe']);
    $reviewer = User::factory()->admin()->create(['name' => 'Supervisor']);
    $expense = Expense::factory()->forUser($employee)->rejected('Wrong category')->create([
        'reviewed_by_id' => $reviewer->id,
    ]);

    $payload = ExpenseApiResponse::resource($expense->fresh(['user', 'reviewedBy']));

    expect($payload['status'])->toBe('rejected')
        ->and($payload['rejection_reason'])->toBe('Wrong category')
        ->and($payload['reviewed_by_id'])->toBe($reviewer->id)
        ->and($payload['reviewed_by_name'])->toBe('Supervisor')
        ->and($payload['reviewed_at'])->not->toBeNull();
});

it('maps a collection of expenses for api responses', function () {
    $employee = User::factory()->create(['name' => 'Jane Doe']);
    $expenses = Expense::factory()->count(2)->forUser($employee)->create([
        'description' => 'Shared description',
    ]);

    $payloads = ExpenseApiResponse::collection($expenses->fresh(['user', 'reviewedBy']));

    expect($payloads)->toHaveCount(2)
        ->and($payloads[0]['employee_name'])->toBe('Jane Doe')
        ->and($payloads[0]['description'])->toBe('Shared description')
        ->and($payloads[1]['employee_name'])->toBe('Jane Doe')
        ->and($payloads[1]['id'])->not->toBe($payloads[0]['id']);
});

it('uses null label defaults when partial labels omit keys', function () {
    $employee = User::factory()->create();
    $expense = Expense::factory()->forUser($employee)->create();

    $payload = ExpenseApiResponse::resource($expense->fresh(['user', 'reviewedBy']), [
        'payment_account_name' => 'Checking',
    ]);

    expect($payload['payment_account_name'])->toBe('Checking')
        ->and($payload['expense_account_name'])->toBeNull()
        ->and($payload['vendor_name'])->toBeNull()
        ->and($payload['customer_name'])->toBeNull()
        ->and($payload['project_name'])->toBeNull();
});
