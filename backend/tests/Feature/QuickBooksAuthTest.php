<?php

use App\Exceptions\QuickBooksException;
use App\Exceptions\QuickBooksOAuthException;
use App\Http\Controllers\Api\QuickBooksAuthController;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QboListCacheService;
use App\Services\QuickBooksConnectionValidationService;
use App\Services\QuickBooksOAuthCallbackService;
use App\Services\QuickBooksService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

covers(QuickBooksAuthController::class);
covers(QuickBooksConnectionValidationService::class);

it('requires authentication for quickbooks connect', function () {
    $this->getJson('/api/quickbooks/connect')->assertUnauthorized();
});

it('returns quickbooks authorization url for authenticated administrators', function () {
    actingAsAdmin();

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

it('rejects quickbooks connect for non-administrators', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/quickbooks/connect')
        ->assertForbidden()
        ->assertJsonPath('error', 'admin_required');
});

it('redirects to admin after oauth callback', function () {
    Queue::fake();

    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create(['realm_id' => '1234567890']);

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

    $this->mock(QuickBooksConnectionValidationService::class, function ($mock) use ($token, $user) {
        $mock->shouldReceive('validateAdministratorConnection')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->is($user)), Mockery::on(fn ($arg) => $arg->is($token)));
    });

    $this->get('/api/quickbooks/callback?code=auth-code&realmId=1234567890&state=secure-state')
        ->assertRedirect('http://localhost:5173?quickbooks=connected');
});

it('binds oauth callback to the user encoded in state without an active session', function () {
    Queue::fake();

    $initiator = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($initiator)->create(['realm_id' => '1234567890']);

    $this->mock(QuickBooksService::class, function ($mock) use ($token, $initiator) {
        $mock->shouldReceive('consumeAuthorizationState')
            ->once()
            ->andReturn(['user_id' => $initiator->id]);
        $mock->shouldReceive('exchangeCode')
            ->once()
            ->with('auth-code', '1234567890', Mockery::on(fn ($arg) => $arg->is($initiator)))
            ->andReturn($token);
    });

    $this->mock(QuickBooksConnectionValidationService::class, function ($mock) use ($token, $initiator) {
        $mock->shouldReceive('validateAdministratorConnection')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->is($initiator)), Mockery::on(fn ($arg) => $arg->is($token)));
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

it('reports quickbooks connection status for authenticated administrators', function () {
    $user = actingAsAdmin();
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-42']);

    $this->getJson('/api/quickbooks/status')
        ->assertOk()
        ->assertJson([
            'connected' => true,
            'realm_id' => 'realm-42',
            'access_token_expired' => false,
            'refresh_token_expired' => false,
            'needs_refresh' => false,
        ])
        ->assertJsonStructure([
            'connected',
            'realm_id',
            'access_token_expires_at',
            'access_token_expired',
            'refresh_token_expired',
            'needs_refresh',
        ]);
});

it('reports refresh pending when the access token is expired', function () {
    $user = actingAsAdmin();
    QuickBooksToken::factory()->forUser($user)->expired()->create(['realm_id' => 'realm-42']);

    $this->getJson('/api/quickbooks/status')
        ->assertOk()
        ->assertJson([
            'connected' => true,
            'realm_id' => 'realm-42',
            'access_token_expired' => true,
            'refresh_token_expired' => false,
            'needs_refresh' => true,
        ]);
});

it('reports disconnected when the refresh token is expired', function () {
    $user = actingAsAdmin();
    QuickBooksToken::factory()->forUser($user)->create([
        'realm_id' => 'realm-42',
        'refresh_token_expires_at' => now()->subMinute(),
    ]);

    $this->getJson('/api/quickbooks/status')
        ->assertOk()
        ->assertJson([
            'connected' => false,
            'realm_id' => 'realm-42',
            'refresh_token_expired' => true,
            'needs_refresh' => false,
        ]);
});

it('rejects quickbooks status for non-administrators', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/quickbooks/status')
        ->assertForbidden()
        ->assertJsonPath('error', 'admin_required');
});

it('reports the latest quickbooks connection for a user', function () {
    $user = actingAsAdmin();
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'old-realm']);
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'new-realm']);

    $this->getJson('/api/quickbooks/status')
        ->assertOk()
        ->assertJsonPath('realm_id', 'new-realm');
});

it('scopes quickbooks status to the authenticated user', function () {
    $user = actingAsAdmin();
    $otherUser = User::factory()->create();
    QuickBooksToken::factory()->forUser($otherUser)->create(['realm_id' => 'other-realm']);

    $this->getJson('/api/quickbooks/status')
        ->assertOk()
        ->assertJson([
            'connected' => false,
        ]);
});

it('disconnects quickbooks for authenticated administrators', function () {
    Http::fake([
        'developer.api.intuit.com/*' => Http::response('', 200),
    ]);

    $user = actingAsAdmin();
    $user->organization->update(['realm_id' => 'realm-42']);
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-42']);

    $this->postJson('/api/quickbooks/disconnect')
        ->assertOk()
        ->assertJson(['connected' => false]);

    expect(QuickBooksToken::query()->count())->toBe(0)
        ->and($user->organization->fresh()->realm_id)->toBeNull();
});

it('rejects quickbooks disconnect for non-administrators', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/quickbooks/disconnect')
        ->assertForbidden()
        ->assertJsonPath('error', 'admin_required');
});

it('requires authentication for quickbooks status', function () {
    $this->getJson('/api/quickbooks/status')->assertUnauthorized();
});

it('requires authentication for quickbooks disconnect', function () {
    $this->postJson('/api/quickbooks/disconnect')->assertUnauthorized();
});

it('rate limits oauth callback requests', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->get('/api/quickbooks/callback');
    }

    $this->get('/api/quickbooks/callback')->assertStatus(429);
});

it('stores oauth state in cache during connect flow', function () {
    $user = actingAsAdmin();

    $service = app(QuickBooksService::class);
    $state = $service->createAuthorizationState($user);

    expect(Cache::get("quickbooks_oauth_state:{$state}"))->toBe(['user_id' => $user->id]);
});

it('invalidates cached quickbooks lists when disconnecting a company', function () {
    Http::fake([
        'developer.api.intuit.com/*' => Http::response('', 200),
    ]);

    $user = actingAsAdmin();
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-42']);
    $cache = app(QboListCacheService::class);

    expect($cache->realmVersion('realm-42'))->toBe(0);

    $this->postJson('/api/quickbooks/disconnect')
        ->assertOk()
        ->assertJson(['connected' => false]);

    expect($cache->realmVersion('realm-42'))->toBe(1);
});

it('disconnects without invalidating list caches when no realm is stored', function () {
    Http::fake([
        'developer.api.intuit.com/*' => Http::response('', 200),
    ]);

    actingAsAdmin();
    $cache = app(QboListCacheService::class);

    $this->postJson('/api/quickbooks/disconnect')
        ->assertOk()
        ->assertJson(['connected' => false]);

    expect($cache->realmVersion('realm-42'))->toBe(0);
});

it('redirects oauth callback errors using the exception reason when provided', function () {
    $this->partialMock(QuickBooksOAuthCallbackService::class, function ($mock) {
        $mock->shouldReceive('exchangeFromCallback')
            ->once()
            ->andThrow(new QuickBooksOAuthException('OAuth failed.', null, 'state_mismatch'));
    });

    $this->get('/api/quickbooks/callback?code=auth-code&realmId=1234567890&state=secure-state')
        ->assertRedirectContains('quickbooks=error')
        ->assertRedirectContains('reason=state_mismatch');
});
