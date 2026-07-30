<?php

use App\Http\Requests\ListTimeEntryRequest;
use Illuminate\Support\Facades\Validator;

covers(ListTimeEntryRequest::class);

it('authorizes authenticated api users via the shared concern', function () {
    $request = new ListTimeEntryRequest;

    expect($request->authorize())->toBeTrue();
});

it('accepts optional pagination and refresh query parameters', function () {
    $request = new ListTimeEntryRequest;
    $validator = Validator::make([
        'start_position' => 5,
        'max_results' => 25,
        'refresh' => true,
    ], $request->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects max_results above the configured cap', function () {
    $validator = Validator::make(['max_results' => 101], (new ListTimeEntryRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('max_results'))->toBeTrue();
});

it('rejects invalid pagination query parameters', function () {
    $validator = Validator::make([
        'start_position' => 0,
        'max_results' => 0,
    ], (new ListTimeEntryRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('start_position'))->toBeTrue()
        ->and($validator->errors()->has('max_results'))->toBeTrue();
});

it('returns defaults for list pagination helpers', function () {
    $request = ListTimeEntryRequest::create('/api/time-entries', 'GET');
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listStartPosition())->toBe(1)
        ->and($request->listMaxResults())->toBe(25)
        ->and($request->listRefresh())->toBeFalse();
});

it('returns validated values from list pagination helpers', function () {
    $request = ListTimeEntryRequest::create('/api/time-entries?start_position=3&max_results=20&refresh=1', 'GET', [
        'start_position' => 3,
        'max_results' => 20,
        'refresh' => true,
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listStartPosition())->toBe(3)
        ->and($request->listMaxResults())->toBe(20)
        ->and($request->listRefresh())->toBeTrue();
});

it('defines pagination and refresh fields in validation rules', function () {
    $rules = (new ListTimeEntryRequest)->rules();

    expect($rules)->toHaveKeys(['start_position', 'max_results', 'refresh'])
        ->and($rules['start_position'])->toContain('integer', 'min:1')
        ->and($rules['max_results'])->toContain('integer', 'min:1', 'max:100')
        ->and($rules['refresh'])->toContain('boolean');
});

it('casts validated pagination helpers to integers', function () {
    $request = ListTimeEntryRequest::create('/api/time-entries?start_position=4&max_results=15', 'GET', [
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
    ], (new ListTimeEntryRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('start_position'))->toBeTrue()
        ->and($validator->errors()->has('max_results'))->toBeTrue();
});

it('accepts max_results at the configured cap', function () {
    $validator = Validator::make(['max_results' => 100], (new ListTimeEntryRequest)->rules());

    expect($validator->passes())->toBeTrue();
});
