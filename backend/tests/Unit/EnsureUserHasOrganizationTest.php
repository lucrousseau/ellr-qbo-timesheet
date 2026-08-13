<?php

use App\Http\Middleware\EnsureUserHasOrganization;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

covers(EnsureUserHasOrganization::class);

it('rejects authenticated users without an organization', function () {
    $user = User::factory()->make([
        'organization_id' => null,
        'is_super_admin' => false,
    ]);
    $user->id = 1;

    $request = Request::create('/api/user', 'GET');
    $request->setUserResolver(fn () => $user);

    try {
        (new EnsureUserHasOrganization)->handle(
            $request,
            fn () => response()->json(['ok' => true]),
        );
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(403)
            ->and($exception->getResponse()->getData(true)['error'])->toBe('organization_required')
            ->and($exception->getResponse()->getData(true)['message'])->toBe('Organization membership is required.');
    }
});

it('allows platform super administrators without an organization when opted in', function () {
    $user = User::factory()->make([
        'organization_id' => null,
        'is_super_admin' => true,
    ]);

    $request = Request::create('/api/user', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = (new EnsureUserHasOrganization)->handle(
        $request,
        fn () => response()->json(['ok' => true]),
        'allow_super_admin',
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['ok'])->toBeTrue();
});

it('rejects platform super administrators without an organization by default', function () {
    $user = User::factory()->make([
        'organization_id' => null,
        'is_super_admin' => true,
    ]);

    $request = Request::create('/api/time-activities', 'GET');
    $request->setUserResolver(fn () => $user);

    try {
        (new EnsureUserHasOrganization)->handle(
            $request,
            fn () => response()->json(['ok' => true]),
        );
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(403)
            ->and($exception->getResponse()->getData(true)['error'])->toBe('organization_required');
    }
});

it('allows authenticated users that belong to an organization', function () {
    $user = User::factory()->make([
        'organization_id' => 1,
    ]);

    $request = Request::create('/api/user', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = (new EnsureUserHasOrganization)->handle(
        $request,
        fn () => response()->json(['ok' => true]),
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['ok'])->toBeTrue();
});

it('allows unauthenticated requests to continue', function () {
    $request = Request::create('/api/user', 'GET');
    $request->setUserResolver(fn () => null);

    $response = (new EnsureUserHasOrganization)->handle(
        $request,
        fn () => response()->json(['ok' => true]),
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['ok'])->toBeTrue();
});
