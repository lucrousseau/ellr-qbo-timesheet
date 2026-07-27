<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

covers(AuthController::class);

it('logs out through the web guard and clears the session', function () {
    $session = Mockery::mock(Session::class);
    $session->shouldReceive('invalidate')->once();
    $session->shouldReceive('regenerateToken')->once();

    $request = Request::create('/api/logout', 'POST');
    $request->setLaravelSession($session);

    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('logout')->once();

    Auth::shouldReceive('guard')->once()->with('web')->andReturn($guard);

    $response = (new AuthController)->logout($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['message' => 'Logged out.']);
});

it('skips session cleanup when the request has no session', function () {
    $guard = Mockery::mock(Guard::class);
    $guard->shouldReceive('logout')->once();

    Auth::shouldReceive('guard')->once()->with('web')->andReturn($guard);

    $request = Request::create('/api/logout', 'POST');

    $response = (new AuthController)->logout($request);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe(['message' => 'Logged out.']);
});
