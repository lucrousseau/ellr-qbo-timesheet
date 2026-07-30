<?php

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\OrganizationAccessService;
use App\Services\QboCustomerListService;
use App\Services\TimeTrackerService;
use App\Services\UserQboCustomerAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

    $service = new UserQboCustomerAssignmentService($customers, $timeTracker, new OrganizationAccessService);

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
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $user->qboCustomers()->create([
        'qbo_customer_ref' => '99',
        'qbo_customer_name' => 'Legacy Corp',
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
    (new UserQboCustomerAssignmentService($customers, $timeTracker, new OrganizationAccessService))
        ->sync($admin, $user, $token, false, ['11']);

    expect($user->fresh()->qboCustomers->pluck('qbo_customer_ref')->all())->toBe(['11']);
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

    $service = new UserQboCustomerAssignmentService($customers, $timeTracker, new OrganizationAccessService);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    expect(fn () => $service->sync($admin, $user, $token, false, ['11', '99']))
        ->toThrow(HttpResponseException::class);
});

it('grants access to all customers and clears stored assignments', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $user->qboCustomers()->create([
        'qbo_customer_ref' => '11',
        'qbo_customer_name' => 'Acme Corp',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldNotReceive('listAllActive');

    $timeTracker = Mockery::mock(TimeTrackerService::class);
    $timeTracker->shouldReceive('sanitizeActiveSessionIfExists')
        ->once()
        ->with($user, $token);

    $service = new UserQboCustomerAssignmentService($customers, $timeTracker, new OrganizationAccessService);

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
        'qbo_employee_name' => 'Jane Doe',
        'qbo_all_customers_access' => true,
    ]);

    $service = new UserQboCustomerAssignmentService(
        Mockery::mock(QboCustomerListService::class),
        Mockery::mock(TimeTrackerService::class),
        new OrganizationAccessService,
    );

    expect($service->describeAccess($user))->toBe([
        'all_customers_access' => true,
        'data' => [],
    ]);
});

it('syncs an empty restricted customer assignment list', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
        'qbo_all_customers_access' => true,
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldNotReceive('listAllActive');

    $timeTracker = Mockery::mock(TimeTrackerService::class);
    $timeTracker->shouldReceive('sanitizeActiveSessionIfExists')
        ->once()
        ->with($user, $token);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    $access = (new UserQboCustomerAssignmentService($customers, $timeTracker, new OrganizationAccessService))
        ->sync($admin, $user, $token, false, []);

    expect($access['all_customers_access'])->toBeFalse()
        ->and($access['data'])->toBe([])
        ->and($user->fresh()->qboCustomers)->toHaveCount(0);
});

it('rejects customer sync for non-timesheet users', function () {
    $token = QuickBooksToken::factory()->make();
    $admin = User::factory()->admin()->create();

    $service = new UserQboCustomerAssignmentService(
        Mockery::mock(QboCustomerListService::class),
        Mockery::mock(TimeTrackerService::class),
        new OrganizationAccessService,
    );

    expect(fn () => $service->sync($admin, $admin, $token, true, []))
        ->toThrow(HttpException::class);
});

it('ignores quickbooks customers without a usable identifier during sync', function () {
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
            ['id' => '', 'display_name' => 'Broken'],
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    $timeTracker = Mockery::mock(TimeTrackerService::class);
    $timeTracker->shouldReceive('sanitizeActiveSessionIfExists')
        ->once()
        ->with($user, $token);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    $access = (new UserQboCustomerAssignmentService($customers, $timeTracker, new OrganizationAccessService))
        ->sync($admin, $user, $token, false, ['11']);

    expect($access['data'])->toBe([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);
});

it('deduplicates normalized customer refs during sync', function () {
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
    $timeTracker->shouldReceive('sanitizeActiveSessionIfExists')
        ->once()
        ->with($user, $token);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    (new UserQboCustomerAssignmentService($customers, $timeTracker, new OrganizationAccessService))
        ->sync($admin, $user, $token, false, ['Customer-11', '11']);

    expect($user->fresh()->qboCustomers)->toHaveCount(1)
        ->and($user->fresh()->qboCustomers->first()->qbo_customer_ref)->toBe('11');
});

it('ignores customer refs that cannot be normalized during sync', function () {
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
    $timeTracker->shouldReceive('sanitizeActiveSessionIfExists')
        ->once()
        ->with($user, $token);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);
    (new UserQboCustomerAssignmentService($customers, $timeTracker, new OrganizationAccessService))
        ->sync($admin, $user, $token, false, ['abc', '11']);

    expect($user->fresh()->qboCustomers)->toHaveCount(1)
        ->and($user->fresh()->qboCustomers->first()->qbo_customer_ref)->toBe('11');
});

it('rejects customer sync for users without a quickbooks employee ref', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '',
        'qbo_employee_name' => null,
    ]);

    $admin = User::factory()->admin()->create(['organization_id' => $user->organization_id]);

    $service = new UserQboCustomerAssignmentService(
        Mockery::mock(QboCustomerListService::class),
        Mockery::mock(TimeTrackerService::class),
        new OrganizationAccessService,
    );

    expect(fn () => $service->sync($admin, $user, $token, true, []))
        ->toThrow(HttpException::class);
});

it('describes restricted customer access with assignment rows', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
        'qbo_all_customers_access' => false,
    ]);
    $user->qboCustomers()->create([
        'qbo_customer_ref' => '11',
        'qbo_customer_name' => 'Acme Corp',
    ]);

    $service = new UserQboCustomerAssignmentService(
        Mockery::mock(QboCustomerListService::class),
        Mockery::mock(TimeTrackerService::class),
        new OrganizationAccessService,
    );

    expect($service->describeAccess($user))->toBe([
        'all_customers_access' => false,
        'data' => [
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ],
    ]);
});
