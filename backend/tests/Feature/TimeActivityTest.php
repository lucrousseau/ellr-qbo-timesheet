<?php

use App\Exceptions\QuickBooksException;
use App\Http\Controllers\Api\TimeActivityController;
use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\User;
use App\Services\QuickBooksService;
use App\Services\TimeActivitySyncService;
use App\Support\TimeActivityTimeValidation;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use QuickBooksOnline\API\Data\IPPTimeActivity;
use QuickBooksOnline\API\DataService\DataService;
use Tests\Support\ActingUser;
use Tests\Support\MockeryCapture;

covers(TimeActivityController::class);

const TIME_ACTIVITY_MIN_TXN_DATE = '2025-07-29';
const TIME_ACTIVITY_LIST_QUERY = "SELECT * FROM TimeActivity WHERE TxnDate >= '".TIME_ACTIVITY_MIN_TXN_DATE."' STARTPOSITION 1 MAXRESULTS 100";
const TIME_ACTIVITY_LIST_NEXT_PAGE_QUERY = "SELECT * FROM TimeActivity WHERE TxnDate >= '".TIME_ACTIVITY_MIN_TXN_DATE."' STARTPOSITION 101 MAXRESULTS 100";

/**
 * @return array<string, mixed>
 */
function featureEmployeeActivity(string $id, string $employeeRef = '7'): array
{
    return [
        'Id' => $id,
        'EmployeeRef' => ['value' => $employeeRef],
        'StartTime' => '2026-07-29T09:00:00',
    ];
}

it('requires authentication for time activities', function () {
    $this->getJson('/api/time-activities')->assertUnauthorized();
});

describe('authenticated time activities', function () {
    beforeEach(function () {
        Carbon::setTestNow('2026-07-29 12:00:00');
        config([
            'quickbooks.time_activities_lookback_steps' => [365],
            'quickbooks.time_activities_lookback_days' => 365,
            'quickbooks.time_activities_list_cache_ttl_minutes' => 0,
        ]);
        actingAsWithQboEmployee();
    });

    it('requires quickbooks connection to list time activities', function () {
        $this->getJson('/api/time-activities')
            ->assertForbidden()
            ->assertJsonPath('error', 'quickbooks_not_connected');
    });

    it('lists time activities for non-admin users using the administrator quickbooks token', function () {
        $admin = User::factory()->admin()->create();
        $token = QuickBooksToken::factory()->forUser($admin)->create();

        $employee = timesheetUserFor($admin, [
            'qbo_employee_ref' => '8',
            'qbo_employee_name' => 'Jane Doe',
        ]);
        Sanctum::actingAs($employee);
        seedListedTimeActivities($token, '8', 1);

        $this->getJson('/api/time-activities')
            ->assertOk()
            ->assertJsonPath('data.0.Id', '1');
    });

    it('lists time activities from quickbooks', function () {
        quickBooksTokenWithListedActivities(ActingUser::current(), '7', 1);

        $this->getJson('/api/time-activities')
            ->assertOk()
            ->assertJsonPath('data.0.Id', '1')
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.max_results', 100)
            ->assertJsonPath('meta.start_position', 1)
            ->assertJsonPath('meta.truncated', false);
    });

    it('enriches listed time activities with client and service names', function () {
        $token = QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        TimeActivitySnapshot::factory()
            ->forRealm($token->realm_id)
            ->forEmployee('7')
            ->create([
                'qbo_id' => '1',
                'customer_ref' => '58',
                'customer_name' => 'Test ABC',
                'item_ref' => '6',
                'item_name' => 'Gardening',
            ]);

        $this->getJson('/api/time-activities')
            ->assertOk()
            ->assertJsonPath('data.0.CustomerRef.name', 'Test ABC')
            ->assertJsonPath('data.0.ItemRef.name', 'Gardening');
    });

    it('paginates time activities with start position and max results', function () {
        quickBooksTokenWithListedActivities(ActingUser::current(), '7', 15);

        $this->getJson('/api/time-activities?start_position=11&max_results=10')
            ->assertOk()
            ->assertJsonPath('data.0.Id', '5')
            ->assertJsonPath('meta.start_position', 11)
            ->assertJsonPath('meta.max_results', 10)
            ->assertJsonPath('meta.count', 5);
    });

    it('marks list responses as truncated when more snapshot rows exist', function () {
        quickBooksTokenWithListedActivities(ActingUser::current(), '7', 101);

        $this->getJson('/api/time-activities')
            ->assertOk()
            ->assertJsonPath('meta.count', 100)
            ->assertJsonPath('meta.truncated', true);
    });

    it('does not mark list as truncated when all rows fit on one page', function () {
        quickBooksTokenWithListedActivities(ActingUser::current(), '7', 50);

        $this->getJson('/api/time-activities')
            ->assertOk()
            ->assertJsonPath('meta.count', 50)
            ->assertJsonPath('meta.truncated', false);
    });

    it('rejects list max results above the configured cap', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();

        $this->getJson('/api/time-activities?max_results=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['max_results']);
    });

    it('returns an empty list when quickbooks query result is not an array', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Query')->once()->andReturn(null);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->getJson('/api/time-activities')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.count', 0)
            ->assertJsonPath('meta.truncated', false);
    });

    it('returns quickbooks errors when listing time activities fails', function () {
        config(['quickbooks.expose_api_errors' => false]);
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $dataService = Mockery::mock(DataService::class);
        $error = Mockery::mock();
        $error->shouldReceive('getResponseBody')->andReturn('query failed');
        $dataService->shouldReceive('Query')->once()->andReturn(null);
        $dataService->shouldReceive('getLastError')->andReturn($error);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->getJson('/api/time-activities')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'QuickBooks API error')
            ->assertJsonMissingPath('error');
    });

    it('exposes quickbooks error details when configured', function () {
        config(['quickbooks.expose_api_errors' => true]);
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();

        $this->mock(TimeActivitySyncService::class, function ($mock) {
            $mock->shouldReceive('reconcileRealm')
                ->once()
                ->andThrow(new QuickBooksException(__('api.quickbooks_api_error'), 'query failed', 422));
        });

        $this->getJson('/api/time-activities')
            ->assertUnprocessable()
            ->assertJsonPath('error', 'query failed');
    });

    it('requires a configured qbo employee to create time activities', function () {
        $user = User::factory()->create(['qbo_employee_ref' => null]);
        Sanctum::actingAs($user);
        QuickBooksToken::factory()->forUser($user)->create();

        $this->postJson('/api/time-activities', [
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
        ])
            ->assertForbidden()
            ->assertJsonPath('error', 'qbo_employee_not_configured');
    });

    it('requires start and end times on create', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();

        $this->postJson('/api/time-activities', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time', 'end_time']);
    });

    it('rejects end time before existing start time on update', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $existing = (object) [
            'Id' => '12',
            'SyncToken' => '1',
            'StartTime' => '2026-07-27T09:00:00',
            'EndTime' => '2026-07-27T18:00:00',
            'EmployeeRef' => (object) ['value' => '7'],
        ];
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->andReturn($existing);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->patchJson('/api/time-activities/12', [
            'end_time' => '2026-07-27T09:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_time']);
    });

    it('validates end time order on create', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();

        $this->postJson('/api/time-activities', [
            'start_time' => '2026-07-27T17:00:00',
            'end_time' => '2026-07-27T09:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_time']);
    });

    it('creates a time activity with description in quickbooks', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '99']);
        $dataService->shouldReceive('FindById')
            ->once()
            ->with('TimeActivity', '99')
            ->andReturn(timeActivityQboEntity('99'));
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->postJson('/api/time-activities', [
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
            'description' => 'Billable work',
        ])->assertCreated();

        expect(MockeryCapture::unwrap($captured)->Description)->toBe('Billable work');
    });

    it('returns quickbooks errors when creating a time activity fails', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $dataService = Mockery::mock(DataService::class);
        $error = Mockery::mock();
        $error->shouldReceive('getResponseBody')->andReturn('create failed');
        $dataService->shouldReceive('Add')->once()->andReturn(null);
        $dataService->shouldReceive('getLastError')->andReturn($error);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->postJson('/api/time-activities', [
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'QuickBooks API error');
    });

    it('creates a time activity in quickbooks', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '99']);
        $dataService->shouldReceive('FindById')
            ->once()
            ->with('TimeActivity', '99')
            ->andReturn(timeActivityQboEntity('99'));
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->postJson('/api/time-activities', [
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
            'description' => 'Client work',
        ])
            ->assertCreated()
            ->assertJsonPath('data.Id', '99');
    });

    it('includes customer reference when provided on create', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '99']);
        $dataService->shouldReceive('FindById')
            ->once()
            ->with('TimeActivity', '99')
            ->andReturn(timeActivityQboEntity('99'));
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->postJson('/api/time-activities', [
            'customer_ref' => '42',
            'customer_name' => 'Acme Corp',
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
        ])->assertCreated();

        expect(MockeryCapture::unwrap($captured)->CustomerRef)->toMatchArray([
            'value' => '42',
            'name' => 'Acme Corp',
        ]);
    });

    it('includes customer reference without name when only ref is provided', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '99']);
        $dataService->shouldReceive('FindById')
            ->once()
            ->with('TimeActivity', '99')
            ->andReturn(timeActivityQboEntity('99'));
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->postJson('/api/time-activities', [
            'customer_ref' => '42',
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
        ])->assertCreated();

        $payload = MockeryCapture::unwrap($captured);
        expect($payload->CustomerRef)->toMatchArray(['value' => '42'])
            ->and(isset($payload->CustomerRef->name))->toBeFalse();
    });

    it('omits description when explicitly null on create', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '99']);
        $dataService->shouldReceive('FindById')
            ->once()
            ->with('TimeActivity', '99')
            ->andReturn(timeActivityQboEntity('99'));
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->postJson('/api/time-activities', [
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
            'description' => null,
        ])->assertCreated();

        $payload = MockeryCapture::unwrap($captured);
        expect(isset($payload->Description))->toBeFalse();
    });

    it('omits empty customer names on create', function () {
        ActingUser::current()->update(['qbo_employee_name' => null]);
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '99']);
        $dataService->shouldReceive('FindById')
            ->once()
            ->with('TimeActivity', '99')
            ->andReturn(timeActivityQboEntity('99'));
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->postJson('/api/time-activities', [
            'customer_ref' => '',
            'customer_name' => '',
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
        ])->assertCreated();

        $payload = MockeryCapture::unwrap($captured);
        expect($payload->EmployeeRef)->toMatchArray(['value' => '7'])
            ->and(isset($payload->EmployeeRef->name))->toBeFalse()
            ->and(isset($payload->CustomerRef))->toBeFalse();
    });

    it('returns 404 when a time activity belongs to another employee', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $activity = (object) [
            'Id' => '1',
            'EmployeeRef' => (object) ['value' => '99'],
        ];
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->with('TimeActivity', '1')->andReturn($activity);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->getJson('/api/time-activities/1')->assertNotFound();
    });

    it('returns 404 when a time activity is missing', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->with('TimeActivity', 'missing')->andReturn(null);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->getJson('/api/time-activities/missing')->assertNotFound();
    });

    it('shows an existing time activity from quickbooks', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $activity = (object) [
            'Id' => '1',
            'Description' => 'Support',
            'EmployeeRef' => (object) ['value' => '7'],
        ];
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->with('TimeActivity', '1')->andReturn($activity);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->getJson('/api/time-activities/1')
            ->assertOk()
            ->assertJsonPath('data.Id', '1');
    });

    it('returns 404 when updating a missing time activity', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->with('TimeActivity', 'missing')->andReturn(null);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->patchJson('/api/time-activities/missing', [
            'description' => 'Updated',
        ])->assertNotFound();
    });

    it('returns 404 when deleting a missing time activity', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->with('TimeActivity', 'missing')->andReturn(null);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->deleteJson('/api/time-activities/missing')->assertNotFound();
    });

    it('returns quickbooks errors when showing a time activity fails', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $dataService = Mockery::mock(DataService::class);
        $error = Mockery::mock();
        $error->shouldReceive('getResponseBody')->andReturn('find failed');
        $dataService->shouldReceive('FindById')->once()->andReturn(null);
        $dataService->shouldReceive('getLastError')->andReturn($error);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->getJson('/api/time-activities/1')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'QuickBooks API error');
    });

    it('updates a time activity in quickbooks', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $existing = new IPPTimeActivity;
        $existing->Id = '12';
        $existing->SyncToken = '1';
        $existing->StartTime = '2026-07-27T09:00:00';
        $existing->EmployeeRef = (object) ['value' => '7'];
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->twice()->with('TimeActivity', '12')->andReturn($existing);
        $dataService->shouldReceive('Update')->once()->andReturn((object) ['Id' => '12']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->patchJson('/api/time-activities/12', [
            'description' => '',
            'end_time' => '2026-07-27T18:00:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.Id', '12');
    });

    it('rejects invalid end time on update', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $existing = (object) [
            'Id' => '12',
            'SyncToken' => '1',
            'StartTime' => '2026-07-27T17:00:00',
            'EmployeeRef' => (object) ['value' => '7'],
        ];
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->andReturn($existing);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->patchJson('/api/time-activities/12', [
            'end_time' => '2026-07-27T09:00:00',
        ])
            ->assertUnprocessable()
            ->assertJson([
                'message' => TimeActivityTimeValidation::endAfterStartMessage(),
                'errors' => [
                    'end_time' => [TimeActivityTimeValidation::endAfterStartMessage()],
                ],
            ]);
    });

    it('updates start time and description in quickbooks', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $existing = new IPPTimeActivity;
        $existing->Id = '12';
        $existing->SyncToken = '1';
        $existing->StartTime = '2026-07-27T09:00:00';
        $existing->EmployeeRef = (object) ['value' => '7'];
        $existing->EndTime = '2026-07-27T18:00:00';
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->twice()->with('TimeActivity', '12')->andReturn($existing);
        $dataService->shouldReceive('Update')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '12']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->patchJson('/api/time-activities/12', [
            'start_time' => '2026-07-27T10:00:00',
            'description' => 'Updated scope',
        ])->assertOk();

        $payload = MockeryCapture::unwrap($captured);
        expect($payload->StartTime)->toBe('2026-07-27T10:00:00')
            ->and($payload->Description)->toBe('Updated scope');
    });

    it('updates billable status in quickbooks', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $existing = new IPPTimeActivity;
        $existing->Id = '12';
        $existing->SyncToken = '1';
        $existing->StartTime = '2026-07-27T09:00:00';
        $existing->EndTime = '2026-07-27T18:00:00';
        $existing->EmployeeRef = (object) ['value' => '7'];
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->twice()->with('TimeActivity', '12')->andReturn($existing);
        $dataService->shouldReceive('Update')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '12']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->patchJson('/api/time-activities/12', [
            'is_billable' => false,
        ])->assertOk();

        expect(MockeryCapture::unwrap($captured)->BillableStatus->value)->toBe('NotBillable');
    });

    it('returns quickbooks errors when updating a time activity fails', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $existing = new IPPTimeActivity;
        $existing->Id = '12';
        $existing->SyncToken = '1';
        $existing->StartTime = '2026-07-27T09:00:00';
        $existing->EmployeeRef = (object) ['value' => '7'];
        $existing->EndTime = '2026-07-27T18:00:00';
        $error = Mockery::mock();
        $error->shouldReceive('getResponseBody')->andReturn('update failed');
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->andReturn($existing);
        $dataService->shouldReceive('Update')->once()->andReturn(null);
        $dataService->shouldReceive('getLastError')->andReturn(null, $error);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->patchJson('/api/time-activities/12', [
            'description' => 'Updated',
        ])->assertUnprocessable();
    });

    it('rejects invalid start time on update', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $existing = (object) [
            'Id' => '12',
            'SyncToken' => '1',
            'EndTime' => '2026-07-27T09:00:00',
            'EmployeeRef' => (object) ['value' => '7'],
        ];
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->andReturn($existing);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->patchJson('/api/time-activities/12', [
            'start_time' => '2026-07-27T17:00:00',
        ])->assertUnprocessable();
    });

    it('rejects partial time updates when existing end time is missing', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $existing = (object) [
            'Id' => '12',
            'SyncToken' => '1',
            'StartTime' => '2026-07-27T09:00:00',
            'EmployeeRef' => (object) ['value' => '7'],
        ];
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->andReturn($existing);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->patchJson('/api/time-activities/12', [
            'start_time' => '2026-07-27T10:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', TimeActivityTimeValidation::incompleteTimeFieldsMessage());
    });

    it('rejects partial time updates when existing start time is missing', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $existing = (object) [
            'Id' => '12',
            'SyncToken' => '1',
            'EndTime' => '2026-07-27T17:00:00',
            'EmployeeRef' => (object) ['value' => '7'],
        ];
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->andReturn($existing);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->patchJson('/api/time-activities/12', [
            'end_time' => '2026-07-27T16:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', TimeActivityTimeValidation::incompleteTimeFieldsMessage());
    });

    it('deletes a time activity in quickbooks', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $existing = (object) [
            'Id' => '12',
            'SyncToken' => '1',
            'EmployeeRef' => (object) ['value' => '7'],
        ];
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->andReturn($existing);
        $dataService->shouldReceive('Delete')->once()->with($existing);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->deleteJson('/api/time-activities/12')->assertNoContent();
    });

    it('returns quickbooks errors when deleting a time activity fails', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->create();
        $existing = (object) [
            'Id' => '12',
            'SyncToken' => '1',
            'EmployeeRef' => (object) ['value' => '7'],
        ];
        $error = Mockery::mock();
        $error->shouldReceive('getResponseBody')->andReturn('delete failed');
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->andReturn($existing);
        $dataService->shouldReceive('Delete')->once()->with($existing);
        $dataService->shouldReceive('getLastError')->andReturn(null, $error);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->deleteJson('/api/time-activities/12')->assertUnprocessable();
    });

    it('refreshes expired quickbooks tokens before api calls', function () {
        $user = ActingUser::current();
        $token = QuickBooksToken::factory()->forUser($user)->expired()->create();
        $refreshed = QuickBooksToken::factory()->forUser($user)->make([
            'id' => $token->id,
            'realm_id' => $token->realm_id,
        ]);

        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Query')
            ->once()
            ->with(TIME_ACTIVITY_LIST_QUERY)
            ->andReturn([]);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService, $token, $refreshed) {
            $mock->shouldReceive('refreshToken')->once()->with(Mockery::on(fn ($arg) => $arg->is($token)))->andReturn($refreshed);
            $mock->shouldReceive('dataService')->once()->with(Mockery::on(fn ($arg) => $arg->is($refreshed)))->andReturn($dataService);
        });

        $this->getJson('/api/time-activities')->assertOk();
    });

    it('returns forbidden when quickbooks token refresh fails', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->expired()->create();

        $this->partialMock(QuickBooksService::class, function ($mock) {
            $mock->shouldReceive('refreshToken')
                ->once()
                ->andThrow(new QuickBooksException('QuickBooks token refresh failed.'));
        });

        $this->getJson('/api/time-activities')
            ->assertForbidden()
            ->assertJsonPath('error', 'quickbooks_expired');
    });

    it('returns service unavailable when quickbooks token refresh is locked', function () {
        QuickBooksToken::factory()->forUser(ActingUser::current())->expired()->create();

        $this->partialMock(QuickBooksService::class, function ($mock) {
            $mock->shouldReceive('refreshToken')
                ->once()
                ->andThrow(new LockTimeoutException);
        });

        $this->getJson('/api/time-activities')
            ->assertStatus(503)
            ->assertJsonPath('error', 'quickbooks_busy');
    });

    it('uses the latest quickbooks token for the authenticated user', function () {
        $user = ActingUser::current();
        QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'old-realm']);
        $latest = QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'new-realm']);

        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Query')
            ->once()
            ->with(TIME_ACTIVITY_LIST_QUERY)
            ->andReturn([]);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService, $latest) {
            $mock->shouldReceive('dataService')
                ->once()
                ->with(Mockery::on(fn ($arg) => $arg->is($latest)))
                ->andReturn($dataService);
        });

        $this->getJson('/api/time-activities')->assertOk();
    });

    it('uses the authenticated users quickbooks token', function () {
        $otherUser = User::factory()->create();
        QuickBooksToken::factory()->forUser($otherUser)->create(['realm_id' => 'other']);

        $this->getJson('/api/time-activities')->assertForbidden();
    });
});
