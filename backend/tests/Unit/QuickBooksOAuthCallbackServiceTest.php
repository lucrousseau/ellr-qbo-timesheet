<?php

use App\Exceptions\QuickBooksOAuthException;
use App\Jobs\ReconcileRealmTimeActivitiesJob;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksConnectionValidationService;
use App\Services\QuickBooksOAuthCallbackService;
use App\Services\QuickBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

covers(QuickBooksOAuthCallbackService::class);
covers(ReconcileRealmTimeActivitiesJob::class);

uses(RefreshDatabase::class);

it('exchanges oauth code for the user encoded in state', function () {
    Queue::fake();

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create(['realm_id' => '1234567890']);

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('consumeAuthorizationState')
        ->once()
        ->with('secure-state')
        ->andReturn(['user_id' => $user->id]);
    $quickBooks->shouldReceive('exchangeCode')
        ->once()
        ->with('auth-code', '1234567890', Mockery::on(fn ($arg) => $arg->is($user)))
        ->andReturn($token);

    $connectionValidation = Mockery::mock(QuickBooksConnectionValidationService::class);
    $connectionValidation->shouldReceive('validateAdministratorConnection')
        ->once()
        ->with(Mockery::on(fn ($arg) => $arg->is($user)), Mockery::on(fn ($arg) => $arg->is($token)));

    (new QuickBooksOAuthCallbackService($quickBooks, $connectionValidation))->exchangeFromCallback(
        'auth-code',
        '1234567890',
        'secure-state',
        null,
    );

    Queue::assertPushed(ReconcileRealmTimeActivitiesJob::class, fn (ReconcileRealmTimeActivitiesJob $job) => $job->tokenId === $token->id);
});

it('rejects oauth callback when session user differs from state user', function () {
    $initiator = User::factory()->create();
    $otherUser = User::factory()->create();

    $quickBooks = Mockery::mock(QuickBooksService::class);
    $quickBooks->shouldReceive('consumeAuthorizationState')
        ->once()
        ->andReturn(['user_id' => $initiator->id]);
    $quickBooks->shouldReceive('exchangeCode')->never();

    (new QuickBooksOAuthCallbackService($quickBooks, Mockery::mock(QuickBooksConnectionValidationService::class)))->exchangeFromCallback(
        'auth-code',
        '1234567890',
        'secure-state',
        $otherUser,
    );
})->throws(QuickBooksOAuthException::class, 'OAuth session mismatch.');

it('redirects to admin with an oauth error reason', function () {
    $response = (new QuickBooksOAuthCallbackService(
        Mockery::mock(QuickBooksService::class),
        Mockery::mock(QuickBooksConnectionValidationService::class),
    ))
        ->redirectError('http://localhost:5173', 'oauth');

    expect($response->getTargetUrl())->toBe('http://localhost:5173?quickbooks=error&reason=oauth');
});
