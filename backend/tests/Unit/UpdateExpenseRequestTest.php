<?php

use App\Enums\ExpensePaymentType;
use App\Http\Requests\UpdateExpenseRequest;
use Illuminate\Support\Facades\Validator;

covers(UpdateExpenseRequest::class);

it('authorizes authenticated api users via the shared concern', function () {
    expect((new UpdateExpenseRequest)->authorize())->toBeTrue();
});

it('accepts partial update payloads', function () {
    $validator = Validator::make([
        'description' => 'Updated notes',
        'is_billable' => false,
    ], (new UpdateExpenseRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('accepts full expense field updates', function () {
    $validator = Validator::make([
        'amount' => 99.99,
        'txn_date' => '2026-08-04',
        'payment_type' => 'Check',
        'payment_account_ref' => '35',
        'expense_account_ref' => '7',
        'vendor_ref' => '56',
        'customer_ref' => '11',
        'project_ref' => '22',
        'description' => 'Updated expense',
        'is_billable' => true,
    ], (new UpdateExpenseRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects non positive amounts on update', function () {
    $validator = Validator::make([
        'amount' => -5,
    ], (new UpdateExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('amount'))->toBeTrue();
});

it('rejects invalid payment types on update', function () {
    $validator = Validator::make([
        'payment_type' => 'Wire',
    ], (new UpdateExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('payment_type'))->toBeTrue();
});

it('accepts every allowed payment type on update', function () {
    foreach (ExpensePaymentType::values() as $paymentType) {
        $validator = Validator::make([
            'payment_type' => $paymentType,
        ], (new UpdateExpenseRequest)->rules());

        expect($validator->passes())->toBeTrue();
    }
});

it('rejects invalid txn_date values on update', function () {
    $validator = Validator::make([
        'txn_date' => 'not-a-date',
    ], (new UpdateExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('txn_date'))->toBeTrue();
});

it('rejects descriptions longer than four thousand characters', function () {
    $validator = Validator::make([
        'description' => str_repeat('a', 4001),
    ], (new UpdateExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('description'))->toBeTrue();
});

it('rejects non boolean is_billable values', function () {
    $validator = Validator::make([
        'is_billable' => 'not-a-boolean',
    ], (new UpdateExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('is_billable'))->toBeTrue();
});

it('defines sometimes rules for all mutable fields', function () {
    $rules = (new UpdateExpenseRequest)->rules();

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
        ->and($rules['amount'])->toContain('sometimes', 'numeric', 'gt:0', 'max:999999999.99')
        ->and($rules['txn_date'])->toContain('sometimes', 'date')
        ->and($rules['payment_account_ref'])->toContain('sometimes', 'string', 'max:255')
        ->and($rules['expense_account_ref'])->toContain('sometimes', 'string', 'max:255')
        ->and($rules['vendor_ref'])->toContain('nullable', 'string', 'max:255')
        ->and($rules['customer_ref'])->toContain('nullable', 'string', 'max:255')
        ->and($rules['project_ref'])->toContain('nullable', 'string', 'max:255')
        ->and($rules['description'])->toContain('nullable', 'string', 'max:4000')
        ->and($rules['is_billable'])->toContain('sometimes', 'boolean');
});
