<?php

use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\OrganizationAccessService;
use App\Services\QboEmployeeService;
use App\Services\QuickBooksService;
use App\Services\QuickBooksTokenResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use QuickBooksOnline\API\DataService\DataService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

covers(QboEmployeeService::class);

uses(RefreshDatabase::class);

/**
 * Builds a QboEmployeeService with tenant access checks for unit tests.
 */
function makeQboEmployeeService(
    QuickBooksService $quickBooks,
    QuickBooksTokenResolverService $tokenResolver,
): QboEmployeeService {
    return new QboEmployeeService($quickBooks, $tokenResolver, new OrganizationAccessService);
}

it('updates a user mapping when the employee exists in quickbooks', function () {
    $admin = User::factory()->admin()->make(['organization_id' => 1]);
    $token = QuickBooksToken::factory()->make();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($admin)->andReturn($token);

    $target = Mockery::mock(User::class)->makePartial();
    $target->id = 1;
    $target->organization_id = 1;
    $target->shouldReceive('isAdmin')->andReturn(false);
    $target->shouldReceive('update')->once()->with([
        'qbo_employee_ref' => '42',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
    ]);
    $target->shouldReceive('fresh')->once()->andReturnSelf();

    $service = makeQboEmployeeService($quickBooks, $tokenResolver);

    expect($service->updateMapping($admin, $target, [
        'qbo_employee_ref' => '42',
    ]))->toBe($target);
});

it('casts numeric employee refs before quickbooks lookup', function () {
    $admin = User::factory()->admin()->make(['organization_id' => 1]);
    $token = QuickBooksToken::factory()->make();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($admin)->andReturn($token);

    $target = Mockery::mock(User::class)->makePartial();
    $target->id = 1;
    $target->organization_id = 1;
    $target->shouldReceive('isAdmin')->andReturn(false);
    $target->shouldReceive('update')->once()->with([
        'qbo_employee_ref' => '42',
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'email' => 'jane@example.com',
    ]);
    $target->shouldReceive('fresh')->once()->andReturnSelf();

    $service = makeQboEmployeeService($quickBooks, $tokenResolver);

    expect($service->updateMapping($admin, $target, [
        'qbo_employee_ref' => 42,
    ]))->toBe($target);
});

it('aborts when the employee does not exist in quickbooks', function () {
    $admin = User::factory()->admin()->make(['organization_id' => 1]);
    $target = User::factory()->make(['organization_id' => 1]);
    $token = QuickBooksToken::factory()->make();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->andReturn($token);

    $service = makeQboEmployeeService($quickBooks, $tokenResolver);

    try {
        $service->updateMapping($admin, $target, ['qbo_employee_ref' => '999']);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => 'QuickBooks employee not found.',
                'error' => 'qbo_employee_invalid',
            ]);
    }
});

it('throws when quickbooks employee lookup fails', function () {
    $admin = User::factory()->admin()->make(['organization_id' => 1]);
    $target = User::factory()->make(['organization_id' => 1]);
    $token = QuickBooksToken::factory()->make();

    $error = Mockery::mock();
    $error->shouldReceive('getResponseBody')->andReturn('lookup failed');

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn($error);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->andReturn($dataService);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->andReturn($token);

    $service = makeQboEmployeeService($quickBooks, $tokenResolver);

    expect(fn () => $service->updateMapping($admin, $target, ['qbo_employee_ref' => '42']))
        ->toThrow(QuickBooksException::class, 'QuickBooks employee lookup failed.');
});

it('rejects employee identity resolution when the email is already used', function () {
    $owner = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($owner)->create();
    User::factory()->create(['email' => 'jane@example.com']);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);

    $service = makeQboEmployeeService($quickBooks, $tokenResolver);

    try {
        $service->resolveEmployeeIdentity($token, '42');
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(422)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => 'QuickBooks employee email is already used by another account.',
                'error' => 'qbo_employee_email_conflict',
            ]);
    }
});

it('syncs a timesheet user from quickbooks identity', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'qbo_employee_ref' => '42',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);

    $service = makeQboEmployeeService($quickBooks, $tokenResolver);
    $synced = $service->syncTimesheetUserFromQuickBooks($user, $token);

    expect($synced->name)->toBe('Jane Doe')
        ->and($synced->email)->toBe('jane@example.com');
});

it('skips sync when quickbooks identity is already applied', function () {
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'qbo_employee_ref' => '42',
    ]);
    $updatedAt = $user->updated_at;

    $service = makeQboEmployeeService(
        Mockery::mock(QuickBooksService::class),
        Mockery::mock(QuickBooksTokenResolverService::class),
    );

    $synced = $service->syncTimesheetUserFromIdentity($user, [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'display_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    expect($synced->is($user))->toBeTrue()
        ->and($synced->fresh()->updated_at?->eq($updatedAt))->toBeTrue();
});

it('allows employee identity resolution for the excluded user email', function () {
    $owner = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($owner)->create();
    $target = User::factory()->create(['email' => 'jane@example.com']);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);

    $service = makeQboEmployeeService($quickBooks, $tokenResolver);

    expect($service->resolveEmployeeIdentity($token, '42', $target))->toBe([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'display_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
});

it('throws when syncing identity onto an email already used by another user', function () {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'qbo_employee_ref' => '42',
    ]);
    User::factory()->create(['email' => 'jane@example.com']);

    $service = makeQboEmployeeService(
        Mockery::mock(QuickBooksService::class),
        Mockery::mock(QuickBooksTokenResolverService::class),
    );

    expect(fn () => $service->syncTimesheetUserFromIdentity($user, [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'display_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]))->toThrow(ValidationException::class);
});

it('rejects employee identity resolution when email is missing', function () {
    $owner = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($owner)->create();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'DisplayName' => 'Jane Doe',
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeService($quickBooks, Mockery::mock(QuickBooksTokenResolverService::class));

    try {
        $service->resolveEmployeeIdentity($token, '42');
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getData(true))->toBe([
            'message' => 'QuickBooks employee has no email address configured.',
            'error' => 'qbo_employee_email_missing',
        ]);
    }
});

it('syncs when only the email address changes', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'jane@example.com',
        'qbo_employee_ref' => '42',
    ]);

    $service = makeQboEmployeeService(
        Mockery::mock(QuickBooksService::class),
        Mockery::mock(QuickBooksTokenResolverService::class),
    );

    $synced = $service->syncTimesheetUserFromIdentity($user, [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'display_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    expect($synced->name)->toBe('Jane Doe');
});

it('returns an empty display name when quickbooks omits DisplayName', function () {
    $owner = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($owner)->create();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeService($quickBooks, Mockery::mock(QuickBooksTokenResolverService::class));

    expect($service->findEmployee($token, '42'))->toBe([
        'first_name' => '',
        'last_name' => '',
        'display_name' => '',
        'email' => 'jane@example.com',
    ]);
});

it('casts numeric quickbooks employee refs during quickbooks sync', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => 42,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeService($quickBooks, Mockery::mock(QuickBooksTokenResolverService::class));

    expect($service->syncTimesheetUserFromQuickBooks($user, $token)->is($user))->toBeTrue();
});

it('throws a translated validation error when sync hits an email conflict', function () {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'qbo_employee_ref' => '42',
    ]);
    User::factory()->create(['email' => 'jane@example.com']);

    $service = makeQboEmployeeService(
        Mockery::mock(QuickBooksService::class),
        Mockery::mock(QuickBooksTokenResolverService::class),
    );

    try {
        $service->syncTimesheetUserFromIdentity($user, [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'display_name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
        expect(false)->toBeTrue('Expected validation exception');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'email' => ['QuickBooks employee email is already used by another account.'],
        ]);
    }
});

it('checks whether a quickbooks employee exists', function () {
    $owner = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($owner)->create();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'DisplayName' => 'Jane Doe',
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeService($quickBooks, Mockery::mock(QuickBooksTokenResolverService::class));

    expect($service->employeeExists($token, '42'))->toBeTrue();
});

it('reports missing quickbooks employees', function () {
    $owner = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($owner)->create();

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '99')->andReturn(null);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $service = makeQboEmployeeService($quickBooks, Mockery::mock(QuickBooksTokenResolverService::class));

    expect($service->employeeExists($token, '99'))->toBeFalse();
});

it('rejects mapping updates for users in another organization', function () {
    $admin = User::factory()->admin()->create();
    $foreignUser = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);

    $service = makeQboEmployeeService(
        Mockery::mock(QuickBooksService::class),
        Mockery::mock(QuickBooksTokenResolverService::class),
    );

    expect(fn () => $service->updateMapping($admin, $foreignUser, ['qbo_employee_ref' => '42']))
        ->toThrow(NotFoundHttpException::class);
});

it('keeps administrator profile fields when updating mapping', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create();
    $target = User::factory()->admin()->create([
        'organization_id' => $admin->organization_id,
        'email' => 'admin-target@example.com',
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('FindById')->once()->with('Employee', '42')->andReturn((object) [
        'Id' => '42',
        'DisplayName' => 'Jane Doe',
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ]);
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('dataService')->once()->with($token)->andReturn($dataService);

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($admin)->andReturn($token);

    $service = makeQboEmployeeService($quickBooks, $tokenResolver);
    $service->updateMapping($admin, $target, ['qbo_employee_ref' => '42']);

    $fresh = $target->fresh();

    expect($fresh->email)->toBe('admin-target@example.com')
        ->and($fresh->qbo_employee_ref)->toBe('42');
});

it('updates changed quickbooks identity fields during sync', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'qbo_employee_ref' => '42',
    ]);

    $service = makeQboEmployeeService(
        Mockery::mock(QuickBooksService::class),
        Mockery::mock(QuickBooksTokenResolverService::class),
    );

    $synced = $service->syncTimesheetUserFromIdentity($user, [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'display_name' => 'Jane Doe',
        'email' => 'old@example.com',
    ]);

    expect($synced->name)->toBe('Jane Doe')
        ->and($synced->email)->toBe('old@example.com');
});
