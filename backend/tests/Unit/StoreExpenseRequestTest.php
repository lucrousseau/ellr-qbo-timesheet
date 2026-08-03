<?php

use App\Enums\ExpensePaymentType;
use App\Http\Requests\StoreExpenseRequest;
use Illuminate\Support\Facades\Validator;

covers(StoreExpenseRequest::class);

it('authorizes authenticated api users via the shared concern', function () {
    expect((new StoreExpenseRequest)->authorize())->toBeTrue();
});

it('accepts a valid expense create payload', function () {
    $validator = Validator::make([
        'amount' => 42.5,
        'txn_date' => '2026-08-03',
        'payment_type' => 'Cash',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'vendor_ref' => '56',
        'customer_ref' => '11',
        'project_ref' => '22',
        'description' => 'AI tooling credits',
        'is_billable' => true,
    ], (new StoreExpenseRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('requires amount txn_date and account references', function () {
    $validator = Validator::make([], (new StoreExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('amount'))->toBeTrue()
        ->and($validator->errors()->has('txn_date'))->toBeTrue()
        ->and($validator->errors()->has('payment_account_ref'))->toBeTrue()
        ->and($validator->errors()->has('expense_account_ref'))->toBeTrue();
});

it('rejects non positive amounts', function () {
    $validator = Validator::make([
        'amount' => 0,
        'txn_date' => '2026-08-03',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
    ], (new StoreExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('amount'))->toBeTrue();
});

it('rejects amounts above the configured maximum', function () {
    $validator = Validator::make([
        'amount' => 1000000000,
        'txn_date' => '2026-08-03',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
    ], (new StoreExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('amount'))->toBeTrue();
});

it('rejects invalid payment types', function () {
    $validator = Validator::make([
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_type' => 'Wire',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
    ], (new StoreExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('payment_type'))->toBeTrue();
});

it('accepts every allowed payment type', function () {
    foreach (ExpensePaymentType::values() as $paymentType) {
        $validator = Validator::make([
            'amount' => 10,
            'txn_date' => '2026-08-03',
            'payment_type' => $paymentType,
            'payment_account_ref' => '35',
            'expense_account_ref' => '7',
        ], (new StoreExpenseRequest)->rules());

        expect($validator->passes())->toBeTrue();
    }
});

it('rejects invalid txn_date values', function () {
    $validator = Validator::make([
        'amount' => 10,
        'txn_date' => 'not-a-date',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
    ], (new StoreExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('txn_date'))->toBeTrue();
});

it('rejects descriptions longer than four thousand characters', function () {
    $validator = Validator::make([
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'description' => str_repeat('a', 4001),
    ], (new StoreExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('description'))->toBeTrue();
});

it('rejects account refs longer than two hundred fifty five characters', function () {
    $longRef = str_repeat('a', 256);
    $validator = Validator::make([
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_account_ref' => $longRef,
        'expense_account_ref' => '7',
    ], (new StoreExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('payment_account_ref'))->toBeTrue();
});

it('rejects non boolean is_billable values', function () {
    $validator = Validator::make([
        'amount' => 10,
        'txn_date' => '2026-08-03',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'is_billable' => 'not-a-boolean',
    ], (new StoreExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('is_billable'))->toBeTrue();
});

it('defines validation rules for every expense field', function () {
    $rules = (new StoreExpenseRequest)->rules();

    expect($rules)->toHaveKeys([
        'amount',
        'txn_date',
        'payment_type',
        'payment_account_ref',
        'expense_account_ref',
        'vendor_ref',
        'customer_ref',
        'project_ref',
        'description',
        'is_billable',
    ])
        ->and($rules['amount'])->toContain('required', 'numeric', 'gt:0', 'max:999999999.99')
        ->and($rules['txn_date'])->toContain('required', 'date')
        ->and($rules['payment_type'])->toContain('sometimes', 'string')
        ->and($rules['payment_account_ref'])->toContain('required', 'string', 'max:255')
        ->and($rules['expense_account_ref'])->toContain('required', 'string', 'max:255')
        ->and($rules['vendor_ref'])->toContain('nullable', 'string', 'max:255')
        ->and($rules['customer_ref'])->toContain('nullable', 'string', 'max:255')
        ->and($rules['project_ref'])->toContain('nullable', 'string', 'max:255')
        ->and($rules['description'])->toContain('nullable', 'string', 'max:4000')
        ->and($rules['is_billable'])->toContain('sometimes', 'boolean');
});
