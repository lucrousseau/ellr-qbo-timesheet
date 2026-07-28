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

it('aborts when the qbo employee ref is empty', function () {
    $user = User::factory()->make(['qbo_employee_ref' => '']);

    try {
        (new QboEmployeeAuthorizationService)->resolveEmployeeRef($user);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(403)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => 'QBO employee is not configured for this user.',
                'error' => 'qbo_employee_not_configured',
            ]);
    }
});

it('aborts when the activity belongs to another employee', function () {
    $user = User::factory()->make(['qbo_employee_ref' => '7']);
    $activity = (object) ['EmployeeRef' => (object) ['value' => '99']];

    try {
        (new QboEmployeeAuthorizationService)->assertActivityBelongsToUser($user, $activity);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(404)
            ->and($exception->getResponse()->getData(true))->toBe(['message' => 'Time activity not found']);
    }
});

it('allows an activity that belongs to the users employee', function () {
    $user = User::factory()->make(['qbo_employee_ref' => '7']);
    $activity = (object) ['EmployeeRef' => (object) ['value' => '7']];

    (new QboEmployeeAuthorizationService)->assertActivityBelongsToUser($user, $activity);

    expect(true)->toBeTrue();
});

it('extracts employee refs from sdk activity objects', function () {
    $service = new QboEmployeeAuthorizationService;
    $stringableRef = new class
    {
        public function __toString(): string
        {
            return 'plain-ref';
        }
    };

    expect($service->extractEmployeeRef((object) ['EmployeeRef' => (object) ['value' => '12']]))->toBe('12')
        ->and($service->extractEmployeeRef((object) ['EmployeeRef' => (object) ['value' => 15]]))->toBe('15')
        ->and($service->extractEmployeeRef((object) ['EmployeeRef' => (object) ['value' => '16']]))->toBe('16')
        ->and($service->extractEmployeeRef((object) ['EmployeeRef' => '15']))->toBe('15')
        ->and($service->extractEmployeeRef((object) ['EmployeeRef' => 7]))->toBe('7')
        ->and($service->extractEmployeeRef((object) ['EmployeeRef' => null]))->toBeNull()
        ->and($service->extractEmployeeRef((object) ['EmployeeRef' => $stringableRef]))->toBe('plain-ref');
});
