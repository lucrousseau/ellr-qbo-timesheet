<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboCustomerListService;
use App\Services\TimeTrackerService;
use App\Services\UserQboCustomerAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

uses(RefreshDatabase::class);

covers(UserQboCustomerAssignmentService::class);

it('validates assignments against the admin customer picker list', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')
        ->once()
        ->with($token, true)
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
            ['id' => '12', 'display_name' => 'Beta LLC'],
        ]);

    $timeTracker = Mockery::mock(TimeTrackerService::class);
    $timeTracker->shouldReceive('sanitizeActiveSessionIfExists')
        ->once()
        ->with($user, $token);

    $service = new UserQboCustomerAssignmentService($customers, $timeTracker);

    $access = $service->sync($user, $token, false, ['11', '12']);

    expect($access['all_customers_access'])->toBeFalse()
        ->and($access['data'])->toHaveCount(2)
        ->and($user->fresh()->qboCustomers->pluck('qbo_customer_ref')->all())->toBe(['11', '12']);
});

it('rejects assignments that are not in the admin customer picker list', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')
        ->once()
        ->with($token, true)
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    $timeTracker = Mockery::mock(TimeTrackerService::class);
    $timeTracker->shouldNotReceive('sanitizeActiveSessionIfExists');

    $service = new UserQboCustomerAssignmentService($customers, $timeTracker);

    expect(fn () => $service->sync($user, $token, false, ['11', '99']))
        ->toThrow(HttpResponseException::class);
});
