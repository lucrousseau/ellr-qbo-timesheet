<?php

use App\Enums\ApiErrorCode;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboEmployeeLoginGuardService;
use App\Services\QboEmployeeService;
use App\Services\QuickBooksTokenResolverService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

covers(QboEmployeeLoginGuardService::class);

uses(RefreshDatabase::class);

function makeLoginGuardService(
    QuickBooksTokenResolverService $tokenResolver,
    QboEmployeeService $qboEmployee,
): QboEmployeeLoginGuardService {
    return new QboEmployeeLoginGuardService($tokenResolver, $qboEmployee);
}

it('skips quickbooks checks for administrators and users without mappings', function () {
    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->never();

    $qboEmployee = Mockery::mock(QboEmployeeService::class);
    $qboEmployee->shouldReceive('findEmployee')->never();

    $service = makeLoginGuardService($tokenResolver, $qboEmployee);

    expect($service->rejectIfEmployeeRemoved(User::factory()->admin()->make(), Request::create('/api/login', 'POST')))
        ->toBeNull();
    expect($service->rejectIfEmployeeRemoved(User::factory()->make(), Request::create('/api/login', 'POST')))
        ->toBeNull();
    expect($service->rejectIfEmployeeRemoved(
        User::factory()->make(['qbo_employee_ref' => '']),
        Request::create('/api/login', 'POST'),
    ))->toBeNull();
});

it('rejects login when the mapped quickbooks employee no longer exists', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);
    $token = QuickBooksToken::factory()->create();

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($user)->andReturn($token);

    $qboEmployee = Mockery::mock(QboEmployeeService::class);
    $qboEmployee->shouldReceive('findEmployee')->once()->with($token, '7')->andReturn(null);

    $service = makeLoginGuardService($tokenResolver, $qboEmployee);
    Auth::login($user);

    $response = $service->rejectIfEmployeeRemoved($user, Request::create('/api/login', 'POST'));

    expect($response?->getStatusCode())->toBe(403)
        ->and($response?->getData(true))->toBe([
            'message' => __('api.qbo_employee_removed'),
            'error' => ApiErrorCode::QboEmployeeRemoved->value,
        ]);
    expect(Auth::check())->toBeFalse();
});

it('rejects login when quickbooks identity sync hits an email conflict', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);
    $token = QuickBooksToken::factory()->create();

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($user)->andReturn($token);

    $qboEmployee = Mockery::mock(QboEmployeeService::class);
    $qboEmployee->shouldReceive('findEmployee')->once()->with($token, '7')->andReturn([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'display_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
    $qboEmployee->shouldReceive('syncTimesheetUserFromIdentity')
        ->once()
        ->andThrow(ValidationException::withMessages(['email' => ['conflict']]));

    $service = makeLoginGuardService($tokenResolver, $qboEmployee);
    Auth::login($user);

    $response = $service->rejectIfEmployeeRemoved($user, Request::create('/api/login', 'POST'));

    expect($response?->getStatusCode())->toBe(403)
        ->and($response?->getData(true))->toBe([
            'message' => __('api.qbo_employee_email_conflict'),
            'error' => ApiErrorCode::QboEmployeeEmailConflict->value,
        ]);
});

it('rejects login when the quickbooks employee email is missing', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($user)->andReturn($token);

    $qboEmployee = Mockery::mock(QboEmployeeService::class);
    $qboEmployee->shouldReceive('findEmployee')->once()->with($token, '7')->andReturn([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'display_name' => 'Jane Doe',
        'email' => null,
    ]);

    $service = makeLoginGuardService($tokenResolver, $qboEmployee);

    $response = $service->rejectIfEmployeeRemoved($user, Request::create('/api/login', 'POST'));

    expect($response?->getStatusCode())->toBe(403)
        ->and($response?->getData(true))->toBe([
            'message' => __('api.qbo_employee_email_missing'),
            'error' => ApiErrorCode::QboEmployeeEmailMissing->value,
        ]);
});

it('allows login when quickbooks identity sync succeeds', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($user)->andReturn($token);

    $qboEmployee = Mockery::mock(QboEmployeeService::class);
    $qboEmployee->shouldReceive('findEmployee')->once()->with($token, '7')->andReturn([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'display_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
    $qboEmployee->shouldReceive('syncTimesheetUserFromIdentity')->once()->andReturn($user);

    $service = makeLoginGuardService($tokenResolver, $qboEmployee);

    expect($service->rejectIfEmployeeRemoved($user, Request::create('/api/login', 'POST')))->toBeNull();
});

it('invalidates the session when denying login', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($user)->andReturn($token);

    $qboEmployee = Mockery::mock(QboEmployeeService::class);
    $qboEmployee->shouldReceive('findEmployee')->once()->with($token, '7')->andReturn(null);

    $service = makeLoginGuardService($tokenResolver, $qboEmployee);

    $request = Request::create('/api/login', 'POST');
    $request->setLaravelSession(app('session.store'));
    $request->session()->start();
    Auth::login($user);

    $service->rejectIfEmployeeRemoved($user, $request);

    expect(Auth::check())->toBeFalse();
});

it('invalidates and regenerates the session token when denying login', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => '7',
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($user)->andReturn($token);

    $qboEmployee = Mockery::mock(QboEmployeeService::class);
    $qboEmployee->shouldReceive('findEmployee')->once()->with($token, '7')->andReturn(null);

    $service = makeLoginGuardService($tokenResolver, $qboEmployee);

    $session = Mockery::mock(Session::class);
    $session->shouldReceive('invalidate')->once();
    $session->shouldReceive('regenerateToken')->once();

    $request = Request::create('/api/login', 'POST');
    $request->setLaravelSession($session);
    Auth::login($user);

    $service->rejectIfEmployeeRemoved($user, $request);

    expect(Auth::check())->toBeFalse();
});

it('casts numeric quickbooks employee refs before lookup', function () {
    $user = User::factory()->create([
        'qbo_employee_ref' => 7,
    ]);
    $token = QuickBooksToken::factory()->forUser($user)->create();

    $tokenResolver = Mockery::mock(QuickBooksTokenResolverService::class);
    $tokenResolver->shouldReceive('resolve')->once()->with($user)->andReturn($token);

    $qboEmployee = Mockery::mock(QboEmployeeService::class);
    $qboEmployee->shouldReceive('findEmployee')->once()->with($token, '7')->andReturn([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'display_name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);
    $qboEmployee->shouldReceive('syncTimesheetUserFromIdentity')->once()->andReturn($user);

    $service = makeLoginGuardService($tokenResolver, $qboEmployee);

    expect($service->rejectIfEmployeeRemoved($user, Request::create('/api/login', 'POST')))->toBeNull();
});
