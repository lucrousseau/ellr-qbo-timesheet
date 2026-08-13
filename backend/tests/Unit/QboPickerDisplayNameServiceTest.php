<?php

use App\Models\ActiveTimeSession;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserQboCustomer;
use App\Services\QboCustomerListService;
use App\Services\QboEmployeeListService;
use App\Services\QboEmployeeService;
use App\Services\QboPickerDisplayNameService;
use App\Services\QboProjectListService;
use App\Services\QboRefDisplayNameService;
use App\Services\QboServiceListService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

covers(QboPickerDisplayNameService::class, QboRefDisplayNameService::class);

/**
 * Builds the display name service with mocked list collaborators.
 *
 * @param  QboEmployeeListService|null  $employees  Employee list mock.
 * @param  QboCustomerListService|null  $customers  Customer list mock.
 * @param  QboProjectListService|null  $projects  Project list mock.
 * @param  QboServiceListService|null  $services  Service list mock.
 * @param  QboEmployeeService|null  $employeeLookup  Employee lookup mock.
 */
function makeQboPickerDisplayNameService(
    ?QboEmployeeListService $employees = null,
    ?QboCustomerListService $customers = null,
    ?QboProjectListService $projects = null,
    ?QboServiceListService $services = null,
    ?QboEmployeeService $employeeLookup = null,
): QboPickerDisplayNameService {
    $customers ??= Mockery::mock(QboCustomerListService::class);
    $projects ??= Mockery::mock(QboProjectListService::class);
    $services ??= Mockery::mock(QboServiceListService::class);

    return new QboPickerDisplayNameService(
        $employees ?? Mockery::mock(QboEmployeeListService::class),
        $customers,
        $projects,
        $services,
        $employeeLookup ?? Mockery::mock(QboEmployeeService::class),
        new QboRefDisplayNameService($customers, $projects, $services),
    );
}

it('resolves employee display names from cached lists', function () {
    $token = QuickBooksToken::factory()->make();

    $employees = Mockery::mock(QboEmployeeListService::class);
    $employees->shouldReceive('listActive')
        ->once()
        ->with($token)
        ->andReturn([
            ['id' => '7', 'display_name' => 'Jane Doe'],
        ]);

    $service = makeQboPickerDisplayNameService($employees);

    expect($service->employeeDisplayName($token, '7'))->toBe('Jane Doe')
        ->and($service->employeeDisplayName($token, null))->toBeNull();
});

it('falls back to quickbooks employee lookup when the cached list misses', function () {
    $token = QuickBooksToken::factory()->make();

    $employees = Mockery::mock(QboEmployeeListService::class);
    $employees->shouldReceive('listActive')->once()->with($token)->andReturn([]);

    $employeeLookup = Mockery::mock(QboEmployeeService::class);
    $employeeLookup->shouldReceive('findEmployee')
        ->once()
        ->with($token, '7')
        ->andReturn(['display_name' => 'Jane Doe']);

    $service = makeQboPickerDisplayNameService($employees, null, null, null, $employeeLookup);

    expect($service->employeeDisplayName($token, '7'))->toBe('Jane Doe');
});

it('builds assigned customer rows from cached customer lists', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);
    $assignment = new UserQboCustomer([
        'user_id' => $user->id,
        'qbo_customer_ref' => '11',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listForUser')
        ->once()
        ->with($user, $token)
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
            ['id' => '12', 'display_name' => 'Beta LLC'],
        ]);

    $service = makeQboPickerDisplayNameService(null, $customers);

    expect($service->assignedCustomerRows($token, $user, [$assignment]))->toBe([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);
});

it('resolves active timer session labels from cached picker lists', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);
    $session = ActiveTimeSession::factory()->for($user)->make([
        'customer_ref' => '11',
        'project_ref' => '22',
        'service_ref' => '33',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listForUser')
        ->once()
        ->with($user, $token)
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    $projects = Mockery::mock(QboProjectListService::class);
    $projects->shouldReceive('listForCustomer')
        ->once()
        ->with($token, '11')
        ->andReturn([
            ['id' => '22', 'display_name' => 'Website redesign'],
        ]);

    $services = Mockery::mock(QboServiceListService::class);
    $services->shouldReceive('listActive')
        ->once()
        ->with($token)
        ->andReturn([
            ['id' => '33', 'display_name' => 'Consulting'],
        ]);

    $service = makeQboPickerDisplayNameService(null, $customers, $projects, $services);

    expect($service->sessionDisplayNames($token, $user, $session))->toBe([
        'customer_name' => 'Acme Corp',
        'project_name' => 'Website redesign',
        'service_name' => 'Consulting',
    ]);
});

it('resolves local time entry labels from cached picker lists', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);
    $entry = TimeEntry::factory()->forUser($user)->make([
        'customer_ref' => '11',
        'project_ref' => '22',
        'item_ref' => '33',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listForUser')
        ->once()
        ->with($user, $token)
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    $projects = Mockery::mock(QboProjectListService::class);
    $projects->shouldReceive('listForCustomer')
        ->once()
        ->with($token, '11')
        ->andReturn([
            ['id' => '22', 'display_name' => 'Website redesign'],
        ]);

    $services = Mockery::mock(QboServiceListService::class);
    $services->shouldReceive('listActive')
        ->once()
        ->with($token)
        ->andReturn([
            ['id' => '33', 'display_name' => 'Consulting'],
        ]);

    $service = makeQboPickerDisplayNameService(null, $customers, $projects, $services);

    expect($service->entryDisplayNames($token, $user, $entry))->toBe([
        'customer_name' => 'Acme Corp',
        'project_name' => 'Website redesign',
        'item_name' => 'Consulting',
    ]);
});

it('resolves snapshot labels from the full customer list', function () {
    $token = QuickBooksToken::factory()->make();

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')
        ->once()
        ->with($token)
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);
    $customers->shouldNotReceive('listForUser');

    $projects = Mockery::mock(QboProjectListService::class);
    $projects->shouldReceive('listForCustomer')
        ->once()
        ->with($token, '11')
        ->andReturn([
            ['id' => '22', 'display_name' => 'Website redesign'],
        ]);
    $projects->shouldNotReceive('findJob');

    $services = Mockery::mock(QboServiceListService::class);
    $services->shouldReceive('listActive')
        ->once()
        ->with($token)
        ->andReturn([
            ['id' => '33', 'display_name' => 'Design'],
        ]);

    $service = makeQboPickerDisplayNameService(null, $customers, $projects, $services);

    expect($service->snapshotDisplayNames($token, '11', '22', '33'))->toBe([
        'customer_name' => 'Acme Corp',
        'project_name' => 'Website redesign',
        'item_name' => 'Design',
    ]);
});

it('resolves snapshot customer refs that point at quickbooks jobs', function () {
    $token = QuickBooksToken::factory()->make();

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')
        ->twice()
        ->with($token)
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);

    $projects = Mockery::mock(QboProjectListService::class);
    $projects->shouldReceive('listForCustomer')->never();
    $projects->shouldReceive('findJob')
        ->once()
        ->with($token, '22')
        ->andReturn([
            'id' => '22',
            'display_name' => 'Website redesign',
            'parent_ref' => '11',
        ]);

    $services = Mockery::mock(QboServiceListService::class);
    $services->shouldReceive('listActive')
        ->once()
        ->with($token)
        ->andReturn([
            ['id' => '33', 'display_name' => 'Design'],
        ]);

    $service = makeQboPickerDisplayNameService(null, $customers, $projects, $services);

    expect($service->snapshotDisplayNames($token, '22', null, '33'))->toBe([
        'customer_name' => 'Acme Corp',
        'project_name' => 'Website redesign',
        'item_name' => 'Design',
    ]);
});

it('uses the full customer list when the user has all-customers access', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_all_customers_access' => true,
    ]);
    $assignment = new UserQboCustomer([
        'user_id' => $user->id,
        'qbo_customer_ref' => '11',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listAllActive')
        ->once()
        ->with($token)
        ->andReturn([
            ['id' => '11', 'display_name' => 'Acme Corp'],
        ]);
    $customers->shouldNotReceive('listForUser');

    $service = makeQboPickerDisplayNameService(null, $customers);

    expect($service->assignedCustomerRows($token, $user, [$assignment]))->toBe([
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ]);
});

it('returns null when employee lookup resolves an empty display name', function () {
    $token = QuickBooksToken::factory()->make();

    $employees = Mockery::mock(QboEmployeeListService::class);
    $employees->shouldReceive('listActive')->once()->with($token)->andReturn([]);

    $employeeLookup = Mockery::mock(QboEmployeeService::class);
    $employeeLookup->shouldReceive('findEmployee')
        ->once()
        ->with($token, '7')
        ->andReturn(['display_name' => '']);

    $service = makeQboPickerDisplayNameService($employees, null, null, null, $employeeLookup);

    expect($service->employeeDisplayName($token, '7'))->toBeNull();
});

it('falls back to the customer ref when the cached list has no display name', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);
    $assignment = new UserQboCustomer([
        'user_id' => $user->id,
        'qbo_customer_ref' => '11',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listForUser')
        ->once()
        ->with($user, $token)
        ->andReturn([]);

    $service = makeQboPickerDisplayNameService(null, $customers);

    expect($service->assignedCustomerRows($token, $user, [$assignment]))->toBe([
        ['id' => '11', 'display_name' => '11'],
    ]);
});

it('skips project lookups when only a project ref is stored on a time entry', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($user)->make([
        'customer_ref' => null,
        'project_ref' => '22',
        'item_ref' => null,
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldNotReceive('listForUser');

    $projects = Mockery::mock(QboProjectListService::class);
    $projects->shouldNotReceive('listForCustomer');

    $services = Mockery::mock(QboServiceListService::class);
    $services->shouldNotReceive('listActive');

    $service = makeQboPickerDisplayNameService(null, $customers, $projects, $services);

    expect($service->entryDisplayNames($token, $user, $entry))->toBe([
        'customer_name' => null,
        'project_name' => null,
        'item_name' => null,
    ]);
});

it('skips project lookups when only a project ref is stored on a timer session', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $session = ActiveTimeSession::factory()->for($user)->make([
        'customer_ref' => null,
        'project_ref' => '22',
        'service_ref' => null,
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldNotReceive('listForUser');

    $projects = Mockery::mock(QboProjectListService::class);
    $projects->shouldNotReceive('listForCustomer');

    $services = Mockery::mock(QboServiceListService::class);
    $services->shouldNotReceive('listActive');

    $service = makeQboPickerDisplayNameService(null, $customers, $projects, $services);

    expect($service->sessionDisplayNames($token, $user, $session))->toBe([
        'customer_name' => null,
        'project_name' => null,
        'service_name' => null,
    ]);
});

it('returns null when employee lookup does not find a match', function () {
    $token = QuickBooksToken::factory()->make();

    $employees = Mockery::mock(QboEmployeeListService::class);
    $employees->shouldReceive('listActive')->once()->with($token)->andReturn([]);

    $employeeLookup = Mockery::mock(QboEmployeeService::class);
    $employeeLookup->shouldReceive('findEmployee')->once()->with($token, '7')->andReturn(null);

    $service = makeQboPickerDisplayNameService($employees, null, null, null, $employeeLookup);

    expect($service->employeeDisplayName($token, '7'))->toBeNull();
});

it('skips invalid customer assignments when building assigned customer rows', function () {
    $token = QuickBooksToken::factory()->make();
    $user = User::factory()->create(['qbo_employee_ref' => '7']);
    $assignment = new UserQboCustomer([
        'user_id' => $user->id,
        'qbo_customer_ref' => 'abc',
    ]);

    $customers = Mockery::mock(QboCustomerListService::class);
    $customers->shouldReceive('listForUser')->once()->with($user, $token)->andReturn([]);

    $service = makeQboPickerDisplayNameService(null, $customers);

    expect($service->assignedCustomerRows($token, $user, [$assignment]))->toBe([]);
});
