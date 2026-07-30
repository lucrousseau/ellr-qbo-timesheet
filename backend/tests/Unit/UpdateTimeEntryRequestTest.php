<?php

use App\Http\Requests\UpdateTimeEntryRequest;
use Illuminate\Support\Facades\Validator;

covers(UpdateTimeEntryRequest::class);

it('authorizes authenticated api users via the shared concern', function () {
    expect((new UpdateTimeEntryRequest)->authorize())->toBeTrue();
});

it('accepts partial update payloads', function () {
    $validator = Validator::make([
        'description' => 'Updated notes',
        'is_billable' => false,
    ], (new UpdateTimeEntryRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('accepts picker field updates', function () {
    $validator = Validator::make([
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website',
        'item_ref' => '33',
        'item_name' => 'Hours',
        'start_time' => '2026-07-27T10:00:00',
        'end_time' => '2026-07-27T18:00:00',
    ], (new UpdateTimeEntryRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects end times that are not after start time', function () {
    $validator = Validator::make([
        'start_time' => '2026-07-27T17:00:00',
        'end_time' => '2026-07-27T09:00:00',
    ], (new UpdateTimeEntryRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('end_time'))->toBeTrue();
});

it('rejects descriptions longer than four thousand characters', function () {
    $validator = Validator::make([
        'description' => str_repeat('a', 4001),
    ], (new UpdateTimeEntryRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('description'))->toBeTrue();
});

it('defines sometimes rules for all mutable fields', function () {
    $rules = (new UpdateTimeEntryRequest)->rules();

    expect($rules)->toHaveKeys([
        'customer_ref',
        'customer_name',
        'project_ref',
        'project_name',
        'item_ref',
        'item_name',
        'start_time',
        'end_time',
        'description',
        'is_billable',
    ])
        ->and($rules['customer_ref'])->toContain('sometimes', 'nullable', 'string', 'max:255')
        ->and($rules['start_time'])->toContain('sometimes', 'date')
        ->and($rules['end_time'])->toContain('sometimes', 'date', 'after:start_time')
        ->and($rules['is_billable'])->toContain('sometimes', 'boolean');
});

it('rejects non boolean is_billable values', function () {
    $validator = Validator::make([
        'is_billable' => 'not-a-boolean',
    ], (new UpdateTimeEntryRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('is_billable'))->toBeTrue();
});
