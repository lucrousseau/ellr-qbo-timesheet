<?php

use App\Exceptions\QuickBooksException;
use App\Http\Controllers\Api\TimeActivityController;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Laravel\Sanctum\Sanctum;
use QuickBooksOnline\API\Data\IPPTimeActivity;
use QuickBooksOnline\API\DataService\DataService;

covers(TimeActivityController::class);

it('requires authentication for time activities', function () {
    $this->getJson('/api/time-activities')->assertUnauthorized();
});

describe('authenticated time activities', function () {
    beforeEach(function () {
        actingAsWithQboEmployee();
    });

    it('requires quickbooks connection to list time activities', function () {
        $this->getJson('/api/time-activities')
            ->assertForbidden()
            ->assertJsonPath('error', 'quickbooks_not_connected');
    });

    it('lists time activities from quickbooks', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Query')
            ->once()
            ->with("SELECT * FROM TimeActivity WHERE EmployeeRef = '7' MAXRESULTS 100")
            ->andReturn([['Id' => '1']]);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->getJson('/api/time-activities')
            ->assertOk()
            ->assertJsonPath('data.0.Id', '1');
    });

    it('returns an empty list when quickbooks query result is not an array', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Query')->once()->andReturn(null);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->getJson('/api/time-activities')
            ->assertOk()
            ->assertJsonPath('data', []);
    });

    it('returns quickbooks errors when listing time activities fails', function () {
        config(['quickbooks.expose_api_errors' => false]);
        QuickBooksToken::factory()->forUser(auth()->user())->create();
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
        QuickBooksToken::factory()->forUser(auth()->user())->create();
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
        QuickBooksToken::factory()->forUser(auth()->user())->create();

        $this->postJson('/api/time-activities', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_time', 'end_time']);
    });

    it('rejects equal start and end times on update', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
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
        QuickBooksToken::factory()->forUser(auth()->user())->create();

        $this->postJson('/api/time-activities', [
            'start_time' => '2026-07-27T17:00:00',
            'end_time' => '2026-07-27T09:00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_time']);
    });

    it('creates a time activity with description in quickbooks', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '99']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->postJson('/api/time-activities', [
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
            'description' => 'Billable work',
        ])->assertCreated();

        expect($captured->Description)->toBe('Billable work');
    });

    it('returns quickbooks errors when creating a time activity fails', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
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
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '99']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
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
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '99']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->postJson('/api/time-activities', [
            'customer_ref' => '42',
            'customer_name' => 'Acme Corp',
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
        ])->assertCreated();

        expect($captured->CustomerRef)->toMatchArray([
            'value' => '42',
            'name' => 'Acme Corp',
        ]);
    });

    it('includes customer reference without name when only ref is provided', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '99']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->postJson('/api/time-activities', [
            'customer_ref' => '42',
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
        ])->assertCreated();

        expect($captured->CustomerRef)->toMatchArray(['value' => '42'])
            ->and(isset($captured->CustomerRef->name))->toBeFalse();
    });

    it('omits description when explicitly null on create', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '99']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->postJson('/api/time-activities', [
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
            'description' => null,
        ])->assertCreated();

        expect(isset($captured->Description))->toBeFalse();
    });

    it('omits empty customer names on create', function () {
        auth()->user()->update(['qbo_employee_name' => null]);
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '99']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->postJson('/api/time-activities', [
            'customer_ref' => '',
            'customer_name' => '',
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
        ])->assertCreated();

        expect($captured->EmployeeRef)->toMatchArray(['value' => '7'])
            ->and(isset($captured->EmployeeRef->name))->toBeFalse()
            ->and(isset($captured->CustomerRef))->toBeFalse();
    });

    it('returns 404 when a time activity belongs to another employee', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
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
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->with('TimeActivity', 'missing')->andReturn(null);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->getJson('/api/time-activities/missing')->assertNotFound();
    });

    it('shows an existing time activity from quickbooks', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
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
        QuickBooksToken::factory()->forUser(auth()->user())->create();
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
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->with('TimeActivity', 'missing')->andReturn(null);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->deleteJson('/api/time-activities/missing')->assertNotFound();
    });

    it('returns quickbooks errors when showing a time activity fails', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
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
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $existing = new IPPTimeActivity;
        $existing->Id = '12';
        $existing->SyncToken = '1';
        $existing->StartTime = '2026-07-27T09:00:00';
        $existing->EmployeeRef = (object) ['value' => '7'];
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->andReturn($existing);
        $dataService->shouldReceive('Update')->once()->andReturn((object) ['Id' => '12']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->patchJson('/api/time-activities/12', [
            'description' => '',
            'end_time' => '2026-07-27T18:00:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.Id', '12');
    });

    it('rejects invalid end time on update', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
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
                'message' => 'The end time field must be a date after start time.',
                'errors' => [
                    'end_time' => ['The end time field must be a date after start time.'],
                ],
            ]);
    });

    it('updates start time and description in quickbooks', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $existing = new IPPTimeActivity;
        $existing->Id = '12';
        $existing->SyncToken = '1';
        $existing->StartTime = '2026-07-27T09:00:00';
        $existing->EmployeeRef = (object) ['value' => '7'];
        $existing->EndTime = '2026-07-27T18:00:00';
        $existing->EmployeeRef = (object) ['value' => '7'];
        $captured = null;
        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('FindById')->once()->andReturn($existing);
        $dataService->shouldReceive('Update')
            ->once()
            ->with(Mockery::capture($captured))
            ->andReturn((object) ['Id' => '12']);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->once()->andReturn($dataService);
        });

        $this->patchJson('/api/time-activities/12', [
            'start_time' => '2026-07-27T10:00:00',
            'description' => 'Updated scope',
        ])->assertOk();

        expect($captured->StartTime)->toBe('2026-07-27T10:00:00')
            ->and($captured->Description)->toBe('Updated scope');
    });

    it('returns quickbooks errors when updating a time activity fails', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
        $existing = new IPPTimeActivity;
        $existing->Id = '12';
        $existing->SyncToken = '1';
        $existing->StartTime = '2026-07-27T09:00:00';
        $existing->EmployeeRef = (object) ['value' => '7'];
        $existing->EndTime = '2026-07-27T18:00:00';
        $existing->EmployeeRef = (object) ['value' => '7'];
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
        QuickBooksToken::factory()->forUser(auth()->user())->create();
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

    it('deletes a time activity in quickbooks', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->create();
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
        QuickBooksToken::factory()->forUser(auth()->user())->create();
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
        $user = auth()->user();
        $token = QuickBooksToken::factory()->forUser($user)->expired()->create();
        $refreshed = QuickBooksToken::factory()->forUser($user)->make([
            'id' => $token->id,
            'realm_id' => $token->realm_id,
        ]);

        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Query')
            ->once()
            ->with("SELECT * FROM TimeActivity WHERE EmployeeRef = '7' MAXRESULTS 100")
            ->andReturn([]);
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->partialMock(QuickBooksService::class, function ($mock) use ($dataService, $token, $refreshed) {
            $mock->shouldReceive('refreshToken')->once()->with(Mockery::on(fn ($arg) => $arg->is($token)))->andReturn($refreshed);
            $mock->shouldReceive('dataService')->once()->with(Mockery::on(fn ($arg) => $arg->is($refreshed)))->andReturn($dataService);
        });

        $this->getJson('/api/time-activities')->assertOk();
    });

    it('returns forbidden when quickbooks token refresh fails', function () {
        QuickBooksToken::factory()->forUser(auth()->user())->expired()->create();

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
        QuickBooksToken::factory()->forUser(auth()->user())->expired()->create();

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
        $user = auth()->user();
        QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'old-realm']);
        $latest = QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'new-realm']);

        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Query')
            ->once()
            ->with("SELECT * FROM TimeActivity WHERE EmployeeRef = '7' MAXRESULTS 100")
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
