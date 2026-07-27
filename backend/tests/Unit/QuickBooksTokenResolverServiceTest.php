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

    try {
        (new QuickBooksTokenResolverService(Mockery::mock(QuickBooksService::class)))->resolve($user);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(403)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => 'QuickBooks is not connected.',
                'error' => 'quickbooks_not_connected',
            ]);
    }
});

it('aborts when quickbooks token refresh fails', function () {
    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create([
        'access_token_expires_at' => now()->subMinute(),
    ]);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('refreshToken')->once()->andThrow(new QuickBooksException('expired'));

    try {
        (new QuickBooksTokenResolverService($quickBooks))->resolve($user);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(403)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => 'QuickBooks connection expired. Please reconnect from the admin app.',
                'error' => 'quickbooks_expired',
            ]);
    }
});

it('aborts when quickbooks refresh lock times out', function () {
    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create([
        'access_token_expires_at' => now()->subMinute(),
    ]);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('refreshToken')->once()->andThrow(new LockTimeoutException);

    try {
        (new QuickBooksTokenResolverService($quickBooks))->resolve($user);
        expect(false)->toBeTrue('Expected abort');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe(503)
            ->and($exception->getResponse()->getData(true))->toBe([
                'message' => 'QuickBooks is busy. Please retry.',
                'error' => 'quickbooks_busy',
            ]);
    }
});

it('resolves an administrator quickbooks token for non-admin users without a token', function () {
    $admin = User::factory()->admin()->create();
    $adminToken = QuickBooksToken::factory()->forUser($admin)->create([
        'access_token_expires_at' => now()->addHour(),
    ]);
    $employee = User::factory()->create([
        'qbo_employee_ref' => '7',
        'qbo_employee_name' => 'Jane Doe',
    ]);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldNotReceive('refreshToken');

    $resolved = (new QuickBooksTokenResolverService($quickBooks))->resolve($employee);

    expect($resolved->id)->toBe($adminToken->id);
});
