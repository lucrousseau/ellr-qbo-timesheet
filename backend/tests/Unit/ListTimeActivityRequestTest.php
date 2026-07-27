<?php

use App\Http\Requests\ListTimeActivityRequest;
use Illuminate\Support\Facades\Validator;

covers(ListTimeActivityRequest::class);

it('authorizes authenticated api users via the shared concern', function () {
    $request = new ListTimeActivityRequest;

    expect($request->authorize())->toBeTrue();
});

it('accepts optional pagination query parameters', function () {
    $request = new ListTimeActivityRequest;
    $validator = Validator::make([
        'start_position' => 5,
        'max_results' => 25,
    ], $request->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects max_results above the configured cap', function () {
    $request = new ListTimeActivityRequest;
    $validator = Validator::make(['max_results' => 101], $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('max_results'))->toBeTrue();
});

it('rejects invalid pagination query parameters', function () {
    $request = new ListTimeActivityRequest;
    $validator = Validator::make([
        'start_position' => 0,
        'max_results' => 0,
    ], $request->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('start_position'))->toBeTrue()
        ->and($validator->errors()->has('max_results'))->toBeTrue();
});

it('returns defaults for list pagination helpers', function () {
    $request = ListTimeActivityRequest::create('/api/time-activities', 'GET');
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listStartPosition())->toBe(1)
        ->and($request->listMaxResults())->toBe((int) config('quickbooks.time_activities_max_results', 100));
});

it('returns validated values from list pagination helpers', function () {
    $request = ListTimeActivityRequest::create('/api/time-activities?start_position=3&max_results=20', 'GET', [
        'start_position' => 3,
        'max_results' => 20,
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listStartPosition())->toBe(3)
        ->and($request->listMaxResults())->toBe(20);
});
