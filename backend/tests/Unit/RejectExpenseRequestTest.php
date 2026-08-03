<?php

use App\Http\Requests\RejectExpenseRequest;
use Illuminate\Support\Facades\Validator;

covers(RejectExpenseRequest::class);

it('authorizes authenticated api users via the shared concern', function () {
    expect((new RejectExpenseRequest)->authorize())->toBeTrue();
});

it('accepts an optional rejection reason', function () {
    $validator = Validator::make([
        'reason' => 'Wrong category',
    ], (new RejectExpenseRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('accepts an empty rejection payload', function () {
    $validator = Validator::make([], (new RejectExpenseRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects reasons longer than four thousand characters', function () {
    $validator = Validator::make([
        'reason' => str_repeat('a', 4001),
    ], (new RejectExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('reason'))->toBeTrue();
});

it('defines nullable string rules for the reason field', function () {
    $rules = (new RejectExpenseRequest)->rules();

    expect($rules)->toHaveKey('reason')
        ->and($rules['reason'])->toContain('nullable', 'string', 'max:4000');
});
