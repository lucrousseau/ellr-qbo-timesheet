<?php

use App\Exceptions\QuickBooksException;
use App\Http\Controllers\Api\QuickBooksAuthController;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

covers(QuickBooksAuthController::class);

it('requires authentication for quickbooks connect', function () {
    $this->getJson('/api/quickbooks/connect')->assertUnauthorized();
});

it('returns quickbooks authorization url for authenticated users', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->mock(QuickBooksService::class, function ($mock) {
        $mock->shouldReceive('createAuthorizationState')->once()->andReturn('secure-state');
        $mock->shouldReceive('authorizationUrl')
            ->once()
            ->with('secure-state')
            ->andReturn('https://appcenter.intuit.com/connect/oauth2');
    });

    $this->getJson('/api/quickbooks/connect')
        ->assertOk()
        ->assertJson([
            'authorization_url' => 'https://appcenter.intuit.com/connect/oauth2',
        ]);
});

it('redirects to admin after oauth callback', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->make(['realm_id' => '1234567890']);

    $this->mock(QuickBooksService::class, function ($mock) use ($token, $user) {
        $mock->shouldReceive('consumeAuthorizationState')
            ->once()
            ->with('secure-state')
            ->andReturn(['user_id' => $user->id]);
        $mock->shouldReceive('exchangeCode')
            ->once()
            ->with('auth-code', '1234567890', Mockery::on(fn ($arg) => $arg->is($user)))
            ->andReturn($token);
    });

    $this->get('/api/quickbooks/callback?code=auth-code&realmId=1234567890&state=secure-state')
        ->assertRedirect('http://localhost:5173?quickbooks=connected');
});

it('binds oauth callback to the user encoded in state without an active session', function () {
    $initiator = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($initiator)->make(['realm_id' => '1234567890']);

    $this->mock(QuickBooksService::class, function ($mock) use ($token, $initiator) {
        $mock->shouldReceive('consumeAuthorizationState')
            ->once()
            ->andReturn(['user_id' => $initiator->id]);
        $mock->shouldReceive('exchangeCode')
            ->once()
            ->with('auth-code', '1234567890', Mockery::on(fn ($arg) => $arg->is($initiator)))
            ->andReturn($token);
    });

    $this->get('/api/quickbooks/callback?code=auth-code&realmId=1234567890&state=secure-state')
        ->assertRedirect('http://localhost:5173?quickbooks=connected');
});

it('rejects oauth callback when session user differs from state user', function () {
    $initiator = User::factory()->create();
    $otherUser = User::factory()->create();

    $this->mock(QuickBooksService::class, function ($mock) use ($initiator) {
        $mock->shouldReceive('consumeAuthorizationState')
            ->once()
            ->andReturn(['user_id' => $initiator->id]);
        $mock->shouldReceive('exchangeCode')->never();
    });

    Sanctum::actingAs($otherUser);

    $this->get('/api/quickbooks/callback?code=auth-code&realmId=1234567890&state=secure-state')
        ->assertRedirectContains('quickbooks=error')
        ->assertRedirectContains('reason=oauth');
});

it('redirects to admin with error when oauth state is invalid', function () {
    $this->mock(QuickBooksService::class, function ($mock) {
        $mock->shouldReceive('consumeAuthorizationState')
            ->once()
            ->andThrow(new QuickBooksException('Invalid or expired OAuth state.'));
    });

    $this->get('/api/quickbooks/callback?code=auth-code&realmId=1234567890&state=bad-state')
        ->assertRedirectContains('quickbooks=error')
        ->assertRedirectContains('reason=connection');
});

it('requires oauth callback parameters', function () {
    $this->get('/api/quickbooks/callback')
        ->assertRedirectContains('quickbooks=error')
        ->assertRedirectContains('reason=missing_params');

    $this->get('/api/quickbooks/callback?code=auth-code&realmId=1234567890')
        ->assertRedirectContains('reason=missing_params');

    $this->get('/api/quickbooks/callback?code=auth-code&state=secure-state')
        ->assertRedirectContains('reason=missing_params');

    $this->get('/api/quickbooks/callback?realmId=1234567890&state=secure-state')
        ->assertRedirectContains('reason=missing_params');
});

it('reports quickbooks connection status for authenticated users', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-42']);

    $this->getJson('/api/quickbooks/status')
        ->assertOk()
        ->assertJson([
            'connected' => true,
            'realm_id' => 'realm-42',
        ])
        ->assertJsonStructure(['connected', 'realm_id', 'access_token_expires_at']);
});

it('reports the latest quickbooks connection for a user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'old-realm']);
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'new-realm']);

    $this->getJson('/api/quickbooks/status')
        ->assertOk()
        ->assertJsonPath('realm_id', 'new-realm');
});

it('scopes quickbooks status to the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    Sanctum::actingAs($user);
    QuickBooksToken::factory()->forUser($otherUser)->create(['realm_id' => 'other-realm']);

    $this->getJson('/api/quickbooks/status')
        ->assertOk()
        ->assertJson([
            'connected' => false,
        ]);
});

it('disconnects quickbooks for authenticated users', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    QuickBooksToken::factory()->forUser($user)->create();

    $this->postJson('/api/quickbooks/disconnect')
        ->assertOk()
        ->assertJson(['connected' => false]);

    expect(QuickBooksToken::query()->count())->toBe(0);
});

it('requires authentication for quickbooks status', function () {
    $this->getJson('/api/quickbooks/status')->assertUnauthorized();
});

it('requires authentication for quickbooks disconnect', function () {
    $this->postJson('/api/quickbooks/disconnect')->assertUnauthorized();
});

it('stores oauth state in cache during connect flow', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $service = app(QuickBooksService::class);
    $state = $service->createAuthorizationState($user);

    expect(Cache::get("quickbooks_oauth_state:{$state}"))->toBe(['user_id' => $user->id]);
});
