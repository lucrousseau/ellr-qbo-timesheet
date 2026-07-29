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

it('uses the configured max results cap in validation rules', function () {
    config(['quickbooks.time_activities_max_results' => 50]);

    $request = new ListTimeActivityRequest;
    $passing = Validator::make(['max_results' => 50], $request->rules());
    $failing = Validator::make(['max_results' => 51], $request->rules());

    expect($passing->passes())->toBeTrue()
        ->and($failing->fails())->toBeTrue()
        ->and($failing->errors()->has('max_results'))->toBeTrue();
});

it('returns the configured default max results from list helpers', function () {
    config(['quickbooks.time_activities_max_results' => 75]);

    $request = ListTimeActivityRequest::create('/api/time-activities', 'GET');
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listMaxResults())->toBe(75);
});

it('defines both pagination fields in validation rules', function () {
    $rules = (new ListTimeActivityRequest)->rules();

    expect($rules)->toHaveKeys(['start_position', 'max_results'])
        ->and($rules['start_position'])->toContain('integer', 'min:1')
        ->and($rules['max_results'])->toContain('integer', 'min:1');
});

it('embeds configured max results directly in validation rules', function () {
    config(['quickbooks.time_activities_max_results' => 42]);

    $rules = (new ListTimeActivityRequest)->rules();

    expect($rules['max_results'])->toContain('max:42');
});

it('casts validated pagination helpers to integers', function () {
    $request = ListTimeActivityRequest::create('/api/time-activities?start_position=4&max_results=15', 'GET', [
        'start_position' => '4',
        'max_results' => '15',
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listStartPosition())->toBe(4)
        ->and($request->listMaxResults())->toBe(15);
});

it('uses default max results in validation rules when config is absent', function () {
    $quickbooks = config('quickbooks');
    unset($quickbooks['time_activities_max_results']);
    config(['quickbooks' => $quickbooks]);

    $rules = (new ListTimeActivityRequest)->rules();

    expect($rules['max_results'])->toContain('max:100');
});

it('accepts max_results at the default configured cap', function () {
    $quickbooks = config('quickbooks');
    unset($quickbooks['time_activities_max_results']);
    config(['quickbooks' => $quickbooks]);

    $validator = Validator::make(['max_results' => 100], (new ListTimeActivityRequest)->rules());

    expect($validator->passes())->toBeTrue();
});

it('rejects max_results above the default configured cap', function () {
    $quickbooks = config('quickbooks');
    unset($quickbooks['time_activities_max_results']);
    config(['quickbooks' => $quickbooks]);

    $validator = Validator::make(['max_results' => 101], (new ListTimeActivityRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('max_results'))->toBeTrue();
});

it('rejects non integer pagination query parameters', function () {
    $validator = Validator::make([
        'start_position' => 'abc',
        'max_results' => 'def',
    ], (new ListTimeActivityRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('start_position'))->toBeTrue()
        ->and($validator->errors()->has('max_results'))->toBeTrue();
});

it('returns integer values from list pagination helpers', function () {
    $request = ListTimeActivityRequest::create('/api/time-activities', 'GET');
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listStartPosition())->toBeInt()
        ->and($request->listMaxResults())->toBeInt();
});

it('returns the default max results when quickbooks config is absent', function () {
    $quickbooks = config('quickbooks');
    unset($quickbooks['time_activities_max_results']);
    config(['quickbooks' => $quickbooks]);

    $request = ListTimeActivityRequest::create('/api/time-activities', 'GET');
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listMaxResults())->toBe(100);
});

it('rejects zero start_position values', function () {
    $validator = Validator::make(['start_position' => 0], (new ListTimeActivityRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('start_position'))->toBeTrue();
});

it('rejects zero max_results values', function () {
    $validator = Validator::make(['max_results' => 0], (new ListTimeActivityRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('max_results'))->toBeTrue();
});

it('casts configured max results to integers in validation rules', function () {
    config(['quickbooks.time_activities_max_results' => '050']);

    $rules = (new ListTimeActivityRequest)->rules();

    expect($rules['max_results'])->toContain('max:50');
});

it('defaults list pagination helpers to one and the configured maximum', function () {
    $quickbooks = config('quickbooks');
    unset($quickbooks['time_activities_max_results']);
    config(['quickbooks' => $quickbooks]);

    $request = ListTimeActivityRequest::create('/api/time-activities', 'GET');
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listStartPosition())->toBe(1)
        ->and($request->listMaxResults())->toBe(100);
});

it('returns the refresh flag from validated query parameters', function () {
    $request = ListTimeActivityRequest::create('/api/time-activities?refresh=1', 'GET', [
        'refresh' => true,
    ]);
    $request->setContainer(app());
    $request->validateResolved();

    expect($request->listRefresh())->toBeTrue();

    $withoutRefresh = ListTimeActivityRequest::create('/api/time-activities', 'GET');
    $withoutRefresh->setContainer(app());
    $withoutRefresh->validateResolved();

    expect($withoutRefresh->listRefresh())->toBeFalse();
});
