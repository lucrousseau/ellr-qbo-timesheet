<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\OrganizationAccessService;
use App\Services\QboCustomerListService;
use App\Services\QboPickerDisplayNameService;
use App\Services\TimeTrackerService;
use App\Services\UserQboCustomerAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

covers(UserQboCustomerAssignmentService::class);

/**
 * Builds the assignment service with optional mocked collaborators.
 *
 * @param  QboCustomerListService|null  $customers  Customer list mock.
 * @param  QboPickerDisplayNameService|null  $displayNames  Display name resolver mock.
 * @param  TimeTrackerService|null  $timeTracker  Timer service mock.
 * @return UserQboCustomerAssignmentService
 */
function makeUserQboCustomerAssignmentService(
    ?QboCustomerListService $customers = null,
    ?QboPickerDisplayNameService $displayNames = null,
    ?TimeTrackerService $timeTracker = null,
): UserQboCustomerAssignmentService {
    if ($displayNames === null) {
        $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
        $displayNames->shouldReceive('assignedCustomerRows')->zeroOrMoreTimes()->andReturn([]);
    }

    return new UserQboCustomerAssignmentService(
        $customers ?? Mockery::mock(QboCustomerListService::class),
        $displayNames,
        $timeTracker ?? Mockery::mock(TimeTrackerService::class),
        new OrganizationAccessService,
    );
}

it('validates assignments against the admin customer picker list', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
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

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldReceive('assignedCustomerRows')
        ->once()
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
            ['id' => '12', 'display_name' => 'Beta LLC'],
        ]);

    $service = makeUserQboCustomerAssignmentService($customers, $displayNames, $timeTracker);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    $access = $service->sync($admin, $user, $token, false, ['11', '12']);

    expect($access['all_customers_access'])->toBeFalse()
        ->and($access['data'])->toHaveCount(2)
        ->and($user->fresh()->qboCustomers->pluck('qbo_customer_ref')->all())->toBe(['11', '12']);
});

it('replaces existing customer assignments during sync', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);
    $user->qboCustomers()->create([
        'qbo_customer_ref' => '99',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')
        ->once()
        ->with($token, true)
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    $timeTracker = Mockery::mock(TimeTrackerService::class);
    $timeTracker->shouldReceive('sanitizeActiveSessionIfExists')
        ->once()
        ->with($user, $token);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    (makeUserQboCustomerAssignmentService($customers, null, $timeTracker))
        ->sync($admin, $user, $token, false, ['11']);

    expect($user->fresh()->qboCustomers->pluck('qbo_customer_ref')->all())->toBe(['11']);
});

it('rejects assignments that are not in the admin customer picker list', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
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

    $service = makeUserQboCustomerAssignmentService($customers, null, $timeTracker);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    expect(fn () => $service->sync($admin, $user, $token, false, ['11', '99']))
        ->toThrow(HttpResponseException::class);
});

it('grants access to all customers and clears stored assignments', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);
    $user->qboCustomers()->create([
        'qbo_customer_ref' => '11',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldNotReceive('listAllActive');

    $timeTracker = Mockery::mock(TimeTrackerService::class);
    $timeTracker->shouldReceive('sanitizeActiveSessionIfExists')
        ->once()
        ->with($user, $token);

    $service = makeUserQboCustomerAssignmentService($customers, null, $timeTracker);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    $access = $service->sync($admin, $user, $token, true, []);

    expect($access)->toBe([
        'all_customers_access' => true,
        'data' => [],
    ])->and($user->fresh()->qbo_all_customers_access)->toBeTrue()
        ->and($user->fresh()->qboCustomers)->toHaveCount(0);
});

it('describes all-customers access without assignment rows', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_all_customers_access' => true,
    ]);
    $token = QuickBooksToken::factory()->make();

    expect(makeUserQboCustomerAssignmentService()->describeAccess($user, $token))->toBe([
        'all_customers_access' => true,
        'data' => [],
    ]);
});

it('syncs an empty restricted customer assignment list', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_all_customers_access' => true,
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldNotReceive('listAllActive');

    $timeTracker = Mockery::mock(TimeTrackerService::class);
    $timeTracker->shouldReceive('sanitizeActiveSessionIfExists')
        ->once()
        ->with($user, $token);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    $access = makeUserQboCustomerAssignmentService($customers, null, $timeTracker)
        ->sync($admin, $user, $token, false, []);

    expect($access['all_customers_access'])->toBeFalse()
        ->and($access['data'])->toBe([])
        ->and($user->fresh()->qboCustomers)->toHaveCount(0);
});

it('rejects customer sync for non-timesheet users', function () {
    $token = QuickBooksToken::factory()->make();
    $admin = User::factory()->admin()->create();

    $service = makeUserQboCustomerAssignmentService();

    expect(fn () => $service->sync($admin, $admin, $token, true, []))
        ->toThrow(HttpException::class);
});

it('ignores quickbooks customers without a usable identifier during sync', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')
        ->once()
        ->with($token, true)
        ->andReturn([
            ['id' => '', 'display_name' => 'Broken'],
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    $timeTracker = Mockery::mock(TimeTrackerService::class);
    $timeTracker->shouldReceive('sanitizeActiveSessionIfExists')
        ->once()
        ->with($user, $token);

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldReceive('assignedCustomerRows')
        ->once()
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    $access = makeUserQboCustomerAssignmentService($customers, $displayNames, $timeTracker)
        ->sync($admin, $user, $token, false, ['11']);

    expect($access['data'])->toBe([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);
});

it('deduplicates normalized customer refs during sync', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')
        ->once()
        ->with($token, true)
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    $timeTracker = Mockery::mock(TimeTrackerService::class);
    $timeTracker->shouldReceive('sanitizeActiveSessionIfExists')
        ->once()
        ->with($user, $token);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    (makeUserQboCustomerAssignmentService($customers, null, $timeTracker))
        ->sync($admin, $user, $token, false, ['Customer-11', '11']);

    expect($user->fresh()->qboCustomers)->toHaveCount(1)
        ->and($user->fresh()->qboCustomers->first()->qbo_customer_ref)->toBe('11');
});

it('ignores customer refs that cannot be normalized during sync', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')
        ->once()
        ->with($token, true)
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    $timeTracker = Mockery::mock(TimeTrackerService::class);
    $timeTracker->shouldReceive('sanitizeActiveSessionIfExists')
        ->once()
        ->with($user, $token);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    (makeUserQboCustomerAssignmentService($customers, null, $timeTracker))
        ->sync($admin, $user, $token, false, ['abc', '11']);

    expect($user->fresh()->qboCustomers)->toHaveCount(1)
        ->and($user->fresh()->qboCustomers->first()->qbo_customer_ref)->toBe('11');
});

it('rejects customer sync for users without a quickbooks employee ref', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '',
    ]);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);

    $service = makeUserQboCustomerAssignmentService();

    expect(fn () => $service->sync($admin, $user, $token, true, []))
        ->toThrow(HttpException::class);
});

it('describes restricted customer access with assignment rows', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_all_customers_access' => false,
    ]);
    $user->qboCustomers()->create([
        'qbo_customer_ref' => '11',
    ]);
    $token = QuickBooksToken::factory()->make();

    $displayNames = Mockery::mock(QboPickerDisplayNameService::class);
    $displayNames->shouldReceive('assignedCustomerRows')
        ->once()
        ->with($token, $user, Mockery::type('Illuminate\Database\Eloquent\Collection'))
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    expect(makeUserQboCustomerAssignmentService(null, $displayNames)->describeAccess($user, $token))->toBe([
        'all_customers_access' => false,
        'data' => [
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ],
    ]);
});
