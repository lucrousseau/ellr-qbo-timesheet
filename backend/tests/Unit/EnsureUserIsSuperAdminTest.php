<?php

use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

covers(EnsureUserIsSuperAdmin::class);

it('allows platform super administrators through', function () {
    $user = User::factory()->superAdmin()->create();
    $request = Request::create('/api/super-admin/organizations', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = app(EnsureUserIsSuperAdmin::class)->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('rejects tenant administrators without super admin privileges', function () {
    $user = User::factory()->admin()->create();
    $request = Request::create('/api/super-admin/organizations', 'GET');
    $request->setUserResolver(fn () => $user);

    try {
        app(EnsureUserIsSuperAdmin::class)->handle($request, fn () => response('ok'));
        expect(false)->toBeTrue('Expected middleware to abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(403)
            ->and($exception->getResponse()->getData(true))->toMatchArray([
                'message' => __('api.super_admin_required'),
                'error' => 'super_admin_required',
            ]);
    }
});

it('rejects unauthenticated requests', function () {
    $request = Request::create('/api/super-admin/organizations', 'GET');
    $request->setUserResolver(fn () => null);

    try {
        app(EnsureUserIsSuperAdmin::class)->handle($request, fn () => response('ok'));
        expect(false)->toBeTrue('Expected middleware to abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(403)
            ->and($exception->getResponse()->getData(true)['error'])->toBe('super_admin_required');
    }
});
