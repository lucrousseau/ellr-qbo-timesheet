<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

it('reads trusted proxies from config for reverse-proxy TLS termination', function () {
    config(['trustedproxy.proxies' => '*']);

    $middleware = new TrustProxies;
    $request = Request::create('https://api.example.com/api/health', 'GET', server: [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
    ]);

    $middleware->handle($request, fn (Request $req) => response('ok'));

    expect($request->isSecure())->toBeTrue();
});

it('does not force trusted proxies when config is empty', function () {
    config(['trustedproxy.proxies' => null]);

    $middleware = new TrustProxies;
    $request = Request::create('http://api.example.com/api/health', 'GET', server: [
        'REMOTE_ADDR' => '10.0.0.1',
        'HTTP_X_FORWARDED_PROTO' => 'https',
    ]);

    $middleware->handle($request, fn (Request $req) => response('ok'));

    expect($request->isSecure())->toBeFalse();
});
