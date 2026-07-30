<?php

use App\Http\Requests\RejectTimeEntryRequest;
use Illuminate\Support\Facades\Validator;

covers(RejectTimeEntryRequest::class);

it('authorizes authenticated api users via the shared concern', function () {
    expect((new RejectTimeEntryRequest)->authorize())->toBeTrue();
});

it('accepts an optional rejection reason', function () {
    $validator = Validator::make([
        'reason' => 'Wrong client',
    ], (new RejectTimeEntryRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('accepts an empty rejection payload', function () {
    $validator = Validator::make([], (new RejectTimeEntryRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects reasons longer than two thousand characters', function () {
    $validator = Validator::make([
        'reason' => str_repeat('a', 2001),
    ], (new RejectTimeEntryRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('reason'))->toBeTrue();
});

it('defines nullable string rules for the reason field', function () {
    $rules = (new RejectTimeEntryRequest)->rules();

    expect($rules)->toHaveKey('reason')
        ->and($rules['reason'])->toContain('nullable', 'string', 'max:2000');
});
