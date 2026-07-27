<?php

use App\Exceptions\QuickBooksException;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use App\Services\QuickBooksTokenResolverService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(QuickBooksTokenResolverService::class);

uses(RefreshDatabase::class);

it('returns a valid quickbooks token without refreshing', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create([
        'access_token_expires_at' => now()->addHour(),
    ]);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldNotReceive('refreshToken');

    $resolved = (new QuickBooksTokenResolverService($quickBooks))->resolve($user);

    expect($resolved->id)->toBe($token->id);
});

it('refreshes an expired quickbooks token', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create([
        'access_token_expires_at' => now()->subMinute(),
    ]);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('refreshToken')->once()->with(Mockery::on(fn ($arg) => $arg->is($token)))->andReturn($token);

    $resolved = (new QuickBooksTokenResolverService($quickBooks))->resolve($user);

    expect($resolved->id)->toBe($token->id);
});

it('aborts when quickbooks is not connected', function () {
    $user = User::factory()->create();

    (new QuickBooksTokenResolverService(Mockery::mock(QuickBooksService::class)))->resolve($user);
})->throws(HttpResponseException::class);

it('aborts when quickbooks token refresh fails', function () {
    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create([
        'access_token_expires_at' => now()->subMinute(),
    ]);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('refreshToken')->once()->andThrow(new QuickBooksException('expired'));

    (new QuickBooksTokenResolverService($quickBooks))->resolve($user);
})->throws(HttpResponseException::class);

it('aborts when quickbooks refresh lock times out', function () {
    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create([
        'access_token_expires_at' => now()->subMinute(),
    ]);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('refreshToken')->once()->andThrow(new LockTimeoutException);

    (new QuickBooksTokenResolverService($quickBooks))->resolve($user);
})->throws(HttpResponseException::class);
