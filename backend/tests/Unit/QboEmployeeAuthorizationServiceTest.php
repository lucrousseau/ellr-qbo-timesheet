<?php

use App\Models\User;
use App\Services\QboEmployeeAuthorizationService;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(QboEmployeeAuthorizationService::class);

it('returns the configured qbo employee ref', function () {
    $user = User::factory()->make(['qbo_employee_ref' => '42']);

    $ref = (new QboEmployeeAuthorizationService)->resolveEmployeeRef($user);

    expect($ref)->toBe('42');
});

it('aborts when the qbo employee ref is missing', function () {
    $user = User::factory()->make(['qbo_employee_ref' => null]);

    (new QboEmployeeAuthorizationService)->resolveEmployeeRef($user);
})->throws(HttpResponseException::class);

it('aborts when the activity belongs to another employee', function () {
    $user = User::factory()->make(['qbo_employee_ref' => '7']);
    $activity = (object) ['EmployeeRef' => (object) ['value' => '99']];

    (new QboEmployeeAuthorizationService)->assertActivityBelongsToUser($user, $activity);
})->throws(HttpResponseException::class);

it('allows an activity that belongs to the users employee', function () {
    $user = User::factory()->make(['qbo_employee_ref' => '7']);
    $activity = (object) ['EmployeeRef' => (object) ['value' => '7']];

    (new QboEmployeeAuthorizationService)->assertActivityBelongsToUser($user, $activity);

    expect(true)->toBeTrue();
});

it('extracts employee refs from sdk activity objects', function () {
    $service = new QboEmployeeAuthorizationService;

    expect($service->extractEmployeeRef((object) ['EmployeeRef' => (object) ['value' => '12']]))->toBe('12')
        ->and($service->extractEmployeeRef((object) ['EmployeeRef' => null]))->toBeNull();
});
