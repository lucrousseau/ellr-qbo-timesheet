<?php

use App\Models\ActiveTimeSession;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboPickerValidationService;
use App\Services\TimeActivityService;
use App\Services\TimeTrackerService;
use App\Support\TimerElapsed;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

covers(TimeTrackerService::class);

uses(RefreshDatabase::class);

it('pauses a running timer using server-owned elapsed time', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create([
        'accumulated_seconds' => 120,
        'running_since' => CarbonImmutable::parse('2026-07-28 11:59:00'),
    ]);

    $service = app(TimeTrackerService::class);
    $session = $service->upsertForUser($user, null, [
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'description' => null,
        'is_running' => false,
    ]);

    expect($session->accumulated_seconds)->toBe(180)
        ->and($session->running_since)->toBeNull()
        ->and($session->isRunning())->toBeFalse();

    CarbonImmutable::setTestNow();
});

it('starts a paused timer with a server timestamp', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create([
        'accumulated_seconds' => 120,
        'running_since' => null,
    ]);

    $service = app(TimeTrackerService::class);
    $session = $service->upsertForUser($user, null, [
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'description' => null,
        'is_running' => true,
    ]);

    expect($session->running_since?->toIso8601String())->toBe('2026-07-28T12:00:00+00:00')
        ->and($session->accumulated_seconds)->toBe(120);

    CarbonImmutable::setTestNow();
});

it('keeps the running segment when the timer stays running', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create([
        'accumulated_seconds' => 120,
        'running_since' => CarbonImmutable::parse('2026-07-28 11:59:00'),
        'description' => 'Support',
    ]);

    $service = app(TimeTrackerService::class);
    $session = $service->upsertForUser($user, null, [
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'description' => 'Updated',
        'is_running' => true,
    ]);

    expect($session->running_since?->toIso8601String())->toBe('2026-07-28T11:59:00+00:00')
        ->and($session->accumulated_seconds)->toBe(120)
        ->and($session->description)->toBe('Updated');

    CarbonImmutable::setTestNow();
});

it('clears running_since when a paused timer stays paused', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create([
        'accumulated_seconds' => 240,
        'running_since' => null,
    ]);

    $service = app(TimeTrackerService::class);
    $session = $service->upsertForUser($user, null, [
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'description' => 'Paused',
        'is_running' => false,
    ]);

    expect($session->accumulated_seconds)->toBe(240)
        ->and($session->running_since)->toBeNull();
});

it('validates picker references before logging time', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => '99',
        'customer_name' => 'Unknown Corp',
        'accumulated_seconds' => 60,
        'running_since' => null,
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidSelections')
            ->once()
            ->andThrow(new HttpResponseException(
                response()->json(['message' => __('api.time_tracker_invalid_customer')], 422),
            ));
    });

    $service = app(TimeTrackerService::class);

    expect(fn () => $service->logForUser($user, $token))
        ->toThrow(HttpResponseException::class);
});

it('logs elapsed time with customer project and service selections', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website redesign',
        'service_ref' => '33',
        'service_name' => 'Consulting',
        'description' => 'Support call',
        'accumulated_seconds' => 3600,
        'running_since' => null,
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidSelections')->once();
    });

    $this->mock(TimeActivityService::class, function ($mock) use ($user, $token) {
        $mock->shouldReceive('createForUser')
            ->once()
            ->with(
                $user,
                $token,
                Mockery::on(fn (array $payload): bool => $payload['customer_ref'] === '11'
                    && $payload['customer_name'] === 'Acme Corp'
                    && $payload['project_ref'] === '22'
                    && $payload['project_name'] === 'Website redesign'
                    && $payload['item_ref'] === '33'
                    && $payload['item_name'] === 'Consulting'
                    && $payload['description'] === 'Support call'
                    && $payload['start_time'] === '2026-07-28T11:00:00+00:00'
                    && $payload['end_time'] === '2026-07-28T12:00:00+00:00'),
            )
            ->andReturn((object) ['Id' => '99']);
    });

    $activity = app(TimeTrackerService::class)->logForUser($user, $token);

    expect($activity->Id)->toBe('99')
        ->and(ActiveTimeSession::query()->where('user_id', $user->id)->exists())->toBeFalse();

    CarbonImmutable::setTestNow();
});

it('rejects logging when no active session exists', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    expect(fn () => app(TimeTrackerService::class)->logForUser($user, $token))
        ->toThrow(HttpResponseException::class);
});

it('rejects logging when elapsed time is zero', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create([
        'accumulated_seconds' => 0,
        'running_since' => null,
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidSelections')->once();
    });

    expect(fn () => app(TimeTrackerService::class)->logForUser($user, $token))
        ->toThrow(HttpResponseException::class);
});

it('logs timer sessions with a single elapsed second', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:01');

    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create([
        'accumulated_seconds' => 1,
        'running_since' => null,
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidSelections')->once();
    });

    $this->mock(TimeActivityService::class, function ($mock) {
        $mock->shouldReceive('createForUser')->once()->andReturn((object) ['Id' => '99']);
    });

    app(TimeTrackerService::class)->logForUser($user, $token);

    CarbonImmutable::setTestNow();
});

it('maps active sessions to api payloads and null when absent', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $session = ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website redesign',
        'service_ref' => '33',
        'service_name' => 'Consulting',
        'description' => 'Support',
        'accumulated_seconds' => 90,
        'running_since' => Carbon::parse('2026-07-28 11:59:00'),
    ]);

    $service = app(TimeTrackerService::class);

    expect($service->toApi(null))->toBeNull()
        ->and($service->toApi($session))->toMatchArray([
            'customer_ref' => '11',
            'customer_name' => 'Acme Corp',
            'project_ref' => '22',
            'project_name' => 'Website redesign',
            'service_ref' => '33',
            'service_name' => 'Consulting',
            'description' => 'Support',
            'accumulated_seconds' => 90,
            'is_running' => true,
        ]);
});

it('discards active sessions for a user', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create();

    app(TimeTrackerService::class)->discardForUser($user);

    expect(ActiveTimeSession::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('finds the active session for a user', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $session = ActiveTimeSession::factory()->for($user)->create(['description' => 'Support']);

    expect(app(TimeTrackerService::class)->findForUser($user)?->description)->toBe('Support');
});

it('persists sanitized picker selections for active sessions', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $user->qboCustomers()->create([
        'qbo_customer_ref' => '12',
        'qbo_customer_name' => 'Beta LLC',
    ]);

    $session = ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website redesign',
        'service_ref' => '33',
        'service_name' => 'Consulting',
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) use ($user, $token) {
        $mock->shouldReceive('sanitizeSessionSelections')
            ->once()
            ->with($user, $token, [
                'customer_ref' => '11',
                'customer_name' => 'Acme Corp',
                'project_ref' => '22',
                'project_name' => 'Website redesign',
                'service_ref' => '33',
                'service_name' => 'Consulting',
            ])
            ->andReturn([
                'customer_ref' => null,
                'customer_name' => null,
                'project_ref' => null,
                'project_name' => null,
                'service_ref' => '33',
                'service_name' => 'Consulting',
            ]);
    });

    $sanitized = app(TimeTrackerService::class)->sanitizeForUser($user, $token, $session);

    expect($sanitized->customer_ref)->toBeNull()
        ->and($sanitized->customer_name)->toBeNull()
        ->and($sanitized->project_ref)->toBeNull()
        ->and($sanitized->project_name)->toBeNull()
        ->and($sanitized->service_ref)->toBe('33')
        ->and($sanitized->service_name)->toBe('Consulting');
});

it('returns the same session when sanitize makes no changes', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);
    $user->qboCustomers()->create([
        'qbo_customer_ref' => '12',
        'qbo_customer_name' => 'Beta LLC',
    ]);

    $session = ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => '12',
        'customer_name' => 'Beta LLC',
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('sanitizeSessionSelections')
            ->once()
            ->andReturnUsing(fn ($userArg, $tokenArg, array $selections) => $selections);
    });

    $sanitized = app(TimeTrackerService::class)->sanitizeForUser($user, $token, $session);

    expect($sanitized->is($session))->toBeTrue()
        ->and($sanitized->customer_ref)->toBe('12');
});

it('does nothing when sanitizeActiveSessionIfExists has no active session', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    app(TimeTrackerService::class)->sanitizeActiveSessionIfExists($user, $token);

    expect(ActiveTimeSession::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('caps paused timers through timer elapsed helpers during api mapping', function () {
    config(['quickbooks.time_tracker_max_accumulated_seconds' => 30]);

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $session = ActiveTimeSession::factory()->for($user)->create([
        'accumulated_seconds' => 120,
        'running_since' => null,
    ]);

    $api = app(TimeTrackerService::class)->toApi($session);

    expect($api['elapsed_seconds'])->toBe(30)
        ->and(TimerElapsed::cap(-5))->toBe(0);
});

it('creates a new timer session when none exists', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $session = app(TimeTrackerService::class)->upsertForUser($user, null, [
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website',
        'service_ref' => '33',
        'service_name' => 'Consulting',
        'description' => 'New timer',
        'is_running' => true,
    ]);

    expect($session->accumulated_seconds)->toBe(0)
        ->and($session->customer_ref)->toBe('11')
        ->and($session->project_ref)->toBe('22')
        ->and($session->service_ref)->toBe('33')
        ->and($session->description)->toBe('New timer')
        ->and($session->running_since)->not->toBeNull();
});

it('validates picker references when a quickbooks token is provided', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) use ($user, $token) {
        $mock->shouldReceive('assertValidSelections')
            ->once()
            ->with($user, $token, Mockery::type('array'));
    });

    app(TimeTrackerService::class)->upsertForUser($user, $token, [
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'description' => null,
        'is_running' => false,
    ]);
});

it('logs elapsed time with refs but without optional names', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => '11',
        'customer_name' => null,
        'project_ref' => '22',
        'project_name' => null,
        'service_ref' => '33',
        'service_name' => null,
        'description' => '',
        'accumulated_seconds' => 60,
        'running_since' => null,
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidSelections')->once();
    });

    $this->mock(TimeActivityService::class, function ($mock) use ($user, $token) {
        $mock->shouldReceive('createForUser')
            ->once()
            ->with(
                $user,
                $token,
                Mockery::on(fn (array $payload): bool => $payload['customer_ref'] === '11'
                    && ! array_key_exists('customer_name', $payload)
                    && $payload['project_ref'] === '22'
                    && ! array_key_exists('project_name', $payload)
                    && $payload['item_ref'] === '33'
                    && ! array_key_exists('item_name', $payload)
                    && ! array_key_exists('description', $payload)),
            )
            ->andReturn((object) ['Id' => '99']);
    });

    app(TimeTrackerService::class)->logForUser($user, $token);

    CarbonImmutable::setTestNow();
});

it('logs elapsed time from a running timer segment', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:01:00');

    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'accumulated_seconds' => 30,
        'running_since' => CarbonImmutable::parse('2026-07-28 12:00:00'),
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidSelections')->once();
    });

    $this->mock(TimeActivityService::class, function ($mock) use ($user, $token) {
        $mock->shouldReceive('createForUser')
            ->once()
            ->with(
                $user,
                $token,
                Mockery::on(fn (array $payload): bool => $payload['start_time'] === '2026-07-28T11:59:30+00:00'
                    && $payload['end_time'] === '2026-07-28T12:01:00+00:00'),
            )
            ->andReturn((object) ['Id' => '99']);
    });

    app(TimeTrackerService::class)->logForUser($user, $token);

    CarbonImmutable::setTestNow();
});

it('returns null when no active session exists for a user', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    expect(app(TimeTrackerService::class)->findForUser($user))->toBeNull();
});

it('maps running_since to iso8601 in api payloads', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $session = ActiveTimeSession::factory()->for($user)->create([
        'accumulated_seconds' => 0,
        'running_since' => CarbonImmutable::parse('2026-07-28 11:59:00'),
    ]);

    expect(app(TimeTrackerService::class)->toApi($session)['running_since'])
        ->toBe('2026-07-28T11:59:00+00:00');

    CarbonImmutable::setTestNow();
});

it('upserts timer state when only is_running is provided', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $session = app(TimeTrackerService::class)->upsertForUser($user, null, [
        'is_running' => false,
    ]);

    expect($session->customer_ref)->toBeNull()
        ->and($session->project_ref)->toBeNull()
        ->and($session->service_ref)->toBeNull()
        ->and($session->description)->toBeNull()
        ->and($session->running_since)->toBeNull();
});

it('passes stored session picker refs to validation when logging', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create([
        'customer_ref' => '11',
        'project_ref' => '22',
        'service_ref' => '33',
        'accumulated_seconds' => 30,
        'running_since' => null,
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) use ($user, $token) {
        $mock->shouldReceive('assertValidSelections')
            ->once()
            ->with($user, $token, [
                'customer_ref' => '11',
                'project_ref' => '22',
                'service_ref' => '33',
            ]);
    });

    $this->mock(TimeActivityService::class, function ($mock) {
        $mock->shouldReceive('createForUser')->once()->andReturn((object) ['Id' => '99']);
    });

    app(TimeTrackerService::class)->logForUser($user, $token);
});

it('treats falsy is_running values as paused', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    ActiveTimeSession::factory()->for($user)->create([
        'accumulated_seconds' => 90,
        'running_since' => null,
    ]);

    $session = app(TimeTrackerService::class)->upsertForUser($user, null, [
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'description' => null,
        'is_running' => 0,
    ]);

    expect($session->isRunning())->toBeFalse()
        ->and($session->accumulated_seconds)->toBe(90);
});

it('casts string is_running values to booleans during upsert', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $session = app(TimeTrackerService::class)->upsertForUser($user, null, [
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'description' => null,
        'is_running' => '0',
    ]);

    expect($session->isRunning())->toBeFalse()
        ->and($session->running_since)->toBeNull();

    CarbonImmutable::setTestNow();
});

it('casts truthy is_running integers to booleans during upsert', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $session = app(TimeTrackerService::class)->upsertForUser($user, null, [
        'customer_ref' => null,
        'customer_name' => null,
        'project_ref' => null,
        'project_name' => null,
        'service_ref' => null,
        'service_name' => null,
        'description' => null,
        'is_running' => 1,
    ]);

    expect($session->isRunning())->toBeTrue()
        ->and($session->running_since?->toIso8601String())->toBe('2026-07-28T12:00:00+00:00');

    CarbonImmutable::setTestNow();
});

it('persists optional picker names independently from refs', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $session = app(TimeTrackerService::class)->upsertForUser($user, null, [
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website',
        'service_ref' => '33',
        'service_name' => 'Consulting',
        'description' => null,
        'is_running' => false,
    ]);

    expect($session->customer_name)->toBe('Acme Corp')
        ->and($session->project_name)->toBe('Website')
        ->and($session->service_name)->toBe('Consulting');
});
