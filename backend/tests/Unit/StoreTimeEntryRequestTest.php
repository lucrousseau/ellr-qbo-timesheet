<?php

use App\Http\Requests\StoreTimeEntryRequest;
use Illuminate\Support\Facades\Validator;

covers(StoreTimeEntryRequest::class);

it('authorizes authenticated api users via the shared concern', function () {
    expect((new StoreTimeEntryRequest)->authorize())->toBeTrue();
});

it('accepts a valid time entry create payload', function () {
    $validator = Validator::make([
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
        'description' => 'Support',
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website',
        'item_ref' => '33',
        'item_name' => 'Hours',
        'is_billable' => true,
    ], (new StoreTimeEntryRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('requires start and end times', function () {
    $validator = Validator::make([], (new StoreTimeEntryRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('start_time'))->toBeTrue()
        ->and($validator->errors()->has('end_time'))->toBeTrue();
});

it('rejects end times that are not after start time', function () {
    $validator = Validator::make([
        'start_time' => '2026-07-27T17:00:00',
        'end_time' => '2026-07-27T09:00:00',
    ], (new StoreTimeEntryRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('end_time'))->toBeTrue();
});

it('rejects descriptions longer than four thousand characters', function () {
    $validator = Validator::make([
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
        'description' => str_repeat('a', 4001),
    ], (new StoreTimeEntryRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('description'))->toBeTrue();
});

it('defines optional picker and billable fields in validation rules', function () {
    $rules = (new StoreTimeEntryRequest)->rules();

    expect($rules)->toHaveKeys([
        'customer_ref',
        'customer_name',
        'project_ref',
        'project_name',
        'item_ref',
        'item_name',
        'description',
        'is_billable',
    ])
        ->and($rules['customer_ref'])->toContain('nullable', 'string', 'max:255')
        ->and($rules['is_billable'])->toContain('sometimes', 'boolean');
});
