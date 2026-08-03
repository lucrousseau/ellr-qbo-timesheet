<?php

use App\Models\Expense;
use App\Models\User;
use App\Support\ExpenseQboPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;

covers(ExpenseQboPayload::class);

uses(RefreshDatabase::class);

it('maps stored expense fields to a quickbooks purchase payload', function () {
    $expense = Expense::factory()->forUser(User::factory()->create())->create([
        'amount' => 42.5,
        'txn_date' => '2026-08-03',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'vendor_ref' => '56',
        'customer_ref' => '11',
        'project_ref' => '22',
        'description' => 'AI credits',
        'is_billable' => true,
    ]);

    $payload = ExpenseQboPayload::fromExpense($expense);

    expect($payload)->toMatchArray([
        'amount' => 42.5,
        'txn_date' => '2026-08-03',
        'payment_type' => 'Cash',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'vendor_ref' => '56',
        'customer_ref' => '11',
        'project_ref' => '22',
        'description' => 'AI credits',
        'is_billable' => true,
    ])->and($payload['amount'])->toBeFloat();
});

it('omits optional refs when expense fields are empty', function () {
    $expense = Expense::factory()->forUser(User::factory()->create())->make([
        'vendor_ref' => null,
        'customer_ref' => null,
        'project_ref' => null,
        'description' => null,
    ]);

    $payload = ExpenseQboPayload::fromExpense($expense);

    expect($payload)->not->toHaveKeys(['vendor_ref', 'customer_ref', 'project_ref'])
        ->and($payload['description'])->toBeNull();
});
