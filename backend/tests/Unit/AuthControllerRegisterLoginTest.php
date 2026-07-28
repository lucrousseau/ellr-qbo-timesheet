<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AuthSessionService;
use App\Support\PasswordPolicy;
use Illuminate\Contracts\Session\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

covers(AuthController::class);

uses(RefreshDatabase::class);

it('regenerates the session after registration when a session is present', function () {
    config(['app.allow_registration' => true]);

    $session = Mockery::mock(Session::class);
    $session->shouldReceive('regenerate')->once();

    $request = RegisterRequest::create('/api/register', 'POST', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => PasswordPolicy::validTestPassword(),
        'password_confirmation' => PasswordPolicy::validTestPassword(),
    ]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setLaravelSession($session);
    $request->validateResolved();

    $authSession = Mockery::mock(AuthSessionService::class);
    $authSession->shouldReceive('sendVerificationIfRequired')->once();

    $response = (new AuthController($authSession))->register($request);

    expect($response->getStatusCode())->toBe(201)
        ->and(User::query()->where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('returns the unverified login response from the auth session service', function () {
    $user = User::factory()->unverified()->make();
    $blocked = response()->json(['message' => 'Email address is not verified.', 'error' => 'email_not_verified'], 403);

    $request = LoginRequest::create('/api/login', 'POST', [
        'email' => 'jane@example.com',
        'password' => PasswordPolicy::validTestPassword(),
    ]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->validateResolved();

    Auth::shouldReceive('attempt')->once()->andReturn(true);
    $request->setUserResolver(fn () => $user);

    $authSession = Mockery::mock(AuthSessionService::class);
    $authSession->shouldReceive('rejectUnverifiedLogin')
        ->once()
        ->with($user, $request)
        ->andReturn($blocked);

    $response = (new AuthController($authSession))->login($request);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(403)
        ->and($response->getData(true)['error'])->toBe('email_not_verified');
});

it('regenerates the session after login when a session is present', function () {
    $user = User::factory()->make();

    $session = Mockery::mock(Session::class);
    $session->shouldReceive('regenerate')->once();

    $request = LoginRequest::create('/api/login', 'POST', [
        'email' => 'jane@example.com',
        'password' => PasswordPolicy::validTestPassword(),
    ]);
    $request->setContainer(app());
    $request->setRedirector(app('redirect'));
    $request->setLaravelSession($session);
    $request->validateResolved();
    $request->setUserResolver(fn () => $user);

    Auth::shouldReceive('attempt')->once()->andReturn(true);

    $authSession = Mockery::mock(AuthSessionService::class);
    $authSession->shouldReceive('rejectUnverifiedLogin')->once()->andReturn(null);

    $response = (new AuthController($authSession))->login($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['user']['email'])->toBe($user->email);
});
