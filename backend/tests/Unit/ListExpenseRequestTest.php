<?php

use App\Http\Requests\ListExpenseRequest;
use Illuminate\Support\Facades\Validator;

covers(ListExpenseRequest::class);

it('authorizes authenticated api users via the shared concern', function () {
    expect((new ListExpenseRequest)->authorize())->toBeTrue();
});

it('accepts optional pagination query parameters', function () {
    $validator = Validator::make([
        'start_position' => 5,
        'max_results' => 25,
    ], (new ListExpenseRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects max_results above the configured cap', function () {
    $validator = Validator::make(['max_results' => 101], (new ListExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('max_results'))->toBeTrue();
});

it('rejects invalid pagination query parameters', function () {
    $validator = Validator::make([
        'start_position' => 0,
        'max_results' => 0,
    ], (new ListExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('start_position'))->toBeTrue()
        ->and($validator->errors()->has('max_results'))->toBeTrue();
});

it('returns defaults for list pagination helpers', function () {
    $request = ListExpenseRequest::create('/api/expenses', 'GET');
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listStartPosition())->toBe(1)
        ->and($request->listMaxResults())->toBe(25);
});

it('returns validated values from list pagination helpers', function () {
    $request = ListExpenseRequest::create('/api/expenses?start_position=3&max_results=20', 'GET', [
        'start_position' => 3,
        'max_results' => 20,
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listStartPosition())->toBe(3)
        ->and($request->listMaxResults())->toBe(20);
});

it('defines pagination fields in validation rules', function () {
    $rules = (new ListExpenseRequest)->rules();

    expect($rules)->toHaveKeys(['start_position', 'max_results'])
        ->and($rules['start_position'])->toContain('sometimes', 'integer', 'min:1')
        ->and($rules['max_results'])->toContain('sometimes', 'integer', 'min:1', 'max:100');
});

it('casts validated pagination helpers to integers', function () {
    $request = ListExpenseRequest::create('/api/expenses?start_position=4&max_results=15', 'GET', [
        'start_position' => '4',
        'max_results' => '15',
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listStartPosition())->toBe(4)
        ->and($request->listMaxResults())->toBe(15);
});

it('rejects non integer pagination query parameters', function () {
    $validator = Validator::make([
        'start_position' => 'abc',
        'max_results' => 'def',
    ], (new ListExpenseRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('start_position'))->toBeTrue()
        ->and($validator->errors()->has('max_results'))->toBeTrue();
});

it('accepts max_results at the configured cap', function () {
    $validator = Validator::make(['max_results' => 100], (new ListExpenseRequest)->rules());

    expect($validator->passes())->toBeTrue();
});
