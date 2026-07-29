<?php

use App\Exceptions\QuickBooksException;
use App\Http\Controllers\Api\QuickBooksWebhookController;
use App\Jobs\ProcessQuickBooksWebhookJob;
use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\User;
use App\Services\QuickBooksWebhookProcessorService;
use App\Services\QuickBooksWebhookVerifierService;
use App\Services\TimeActivitySnapshotService;
use App\Services\TimeActivitySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

covers(QuickBooksWebhookController::class);
covers(QuickBooksWebhookProcessorService::class);
covers(ProcessQuickBooksWebhookJob::class);
covers(QuickBooksWebhookVerifierService::class);

uses(RefreshDatabase::class);

/**
 * @return array{payload: string, signature: string}
 */
function signedQuickBooksWebhookPayload(array $body, string $verifier = 'verifier-token'): array
{
    $payload = json_encode($body, JSON_THROW_ON_ERROR);
    $signature = base64_encode(hash_hmac('sha256', $payload, $verifier, true));

    return compact('payload', 'signature');
}

/**
 * @param  array{payload: string, signature: string}  $signed
 */
function postSignedQuickBooksWebhook(TestCase $test, array $signed): TestResponse
{
    return $test->call(
        'POST',
        '/api/quickbooks/webhook',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_INTUIT_SIGNATURE' => $signed['signature']],
        $signed['payload'],
    );
}

it('rejects webhook requests with an invalid signature', function () {
    config(['quickbooks.webhook_verifier' => 'verifier-token']);

    $this->postJson('/api/quickbooks/webhook', ['eventNotifications' => []], [
        'intuit-signature' => 'invalid',
    ])->assertUnauthorized();
});

it('rejects oversized webhook payloads before signature verification', function () {
    Queue::fake();
    config(['quickbooks.webhook_max_payload_bytes' => 32]);

    $this->call(
        'POST',
        '/api/quickbooks/webhook',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_INTUIT_SIGNATURE' => 'invalid-signature'],
        str_repeat('a', 33),
    )->assertStatus(413);

    Queue::assertNothingPushed();
});

it('rejects signed webhook payloads that exceed the notification limit', function () {
    Queue::fake();
    config([
        'quickbooks.webhook_verifier' => 'verifier-token',
        'quickbooks.webhook_max_notifications' => 1,
    ]);

    $signed = signedQuickBooksWebhookPayload([
        'eventNotifications' => [
            ['realmId' => '1'],
            ['realmId' => '2'],
        ],
    ]);

    postSignedQuickBooksWebhook($this, $signed)->assertStatus(422);

    Queue::assertNothingPushed();
});

it('queues webhook payloads with a valid signature', function () {
    Queue::fake();
    config(['quickbooks.webhook_verifier' => 'verifier-token']);

    $signed = signedQuickBooksWebhookPayload([
        'eventNotifications' => [
            [
                'realmId' => '123',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '9', 'operation' => 'Update'],
                    ],
                ],
            ],
        ],
    ]);

    postSignedQuickBooksWebhook($this, $signed)
        ->assertOk()
        ->assertJsonPath('status', 'accepted');

    Queue::assertPushed(ProcessQuickBooksWebhookJob::class);
});

it('rejects signed webhook payloads that exceed the byte limit', function () {
    Queue::fake();
    config([
        'quickbooks.webhook_verifier' => 'verifier-token',
        'quickbooks.webhook_max_payload_bytes' => 32,
    ]);

    $signed = signedQuickBooksWebhookPayload([
        'eventNotifications' => [
            ['realmId' => '123', 'dataChangeEvent' => ['entities' => []]],
        ],
    ]);

    postSignedQuickBooksWebhook($this, $signed)->assertStatus(413);

    Queue::assertNothingPushed();
});

it('rejects signed webhook payloads that exceed the entity limit', function () {
    Queue::fake();
    config([
        'quickbooks.webhook_verifier' => 'verifier-token',
        'quickbooks.webhook_max_entities_per_notification' => 1,
    ]);

    $signed = signedQuickBooksWebhookPayload([
        'eventNotifications' => [
            [
                'realmId' => '123',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '1', 'operation' => 'Update'],
                        ['name' => 'TimeActivity', 'id' => '2', 'operation' => 'Update'],
                    ],
                ],
            ],
        ],
    ]);

    postSignedQuickBooksWebhook($this, $signed)->assertStatus(422);

    Queue::assertNothingPushed();
});

it('does not log quickbooks response bodies when api errors are hidden', function () {
    config(['quickbooks.expose_api_errors' => false]);

    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('syncOneById')
        ->once()
        ->andThrow(new QuickBooksException('QuickBooks API error.', 'secret-body', 422));

    $processor = new QuickBooksWebhookProcessorService(
        $sync,
        app(TimeActivitySnapshotService::class),
    );

    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-1']);

    $job = new ProcessQuickBooksWebhookJob([
        'eventNotifications' => [
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '9', 'operation' => 'Update'],
                    ],
                ],
            ],
        ],
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->with('QuickBooks webhook sync failed', Mockery::on(function (array $context): bool {
            return ($context['message'] ?? '') === 'QuickBooks API error.'
                && ! array_key_exists('response_body', $context);
        }));

    expect(fn () => $job->handle($processor))->toThrow(QuickBooksException::class);
});

it('logs quickbooks response bodies when api errors are exposed', function () {
    config(['quickbooks.expose_api_errors' => true]);

    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('syncOneById')
        ->once()
        ->andThrow(new QuickBooksException('QuickBooks API error.', 'secret-body', 422));

    $processor = new QuickBooksWebhookProcessorService(
        $sync,
        app(TimeActivitySnapshotService::class),
    );

    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-1']);

    $job = new ProcessQuickBooksWebhookJob([
        'eventNotifications' => [
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '9', 'operation' => 'Update'],
                    ],
                ],
            ],
        ],
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->with('QuickBooks webhook sync failed', Mockery::on(function (array $context): bool {
            return ($context['message'] ?? '') === 'QuickBooks API error.'
                && ($context['response_body'] ?? '') === 'secret-body';
        }));

    expect(fn () => $job->handle($processor))->toThrow(QuickBooksException::class);
});

it('soft-deletes snapshots when a delete webhook is received', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-1']);
    $snapshot = TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => '55']);

    app(QuickBooksWebhookProcessorService::class)->process([
        'eventNotifications' => [
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '55', 'operation' => 'Delete'],
                    ],
                ],
            ],
        ],
    ]);

    expect(TimeActivitySnapshot::query()->find($snapshot->id))->toBeNull()
        ->and(TimeActivitySnapshot::withTrashed()->find($snapshot->id))->not->toBeNull();
});

it('soft-deletes snapshots when delete operation casing varies', function () {
    $user = User::factory()->create();
    $token = QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-1']);
    $snapshot = TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => '55']);

    app(QuickBooksWebhookProcessorService::class)->process([
        'eventNotifications' => [
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '55', 'operation' => 'delete'],
                    ],
                ],
            ],
        ],
    ]);

    expect(TimeActivitySnapshot::query()->find($snapshot->id))->toBeNull();
});

it('rethrows quickbooks errors from webhook sync jobs', function () {
    $sync = Mockery::mock(TimeActivitySyncService::class);
    $sync->shouldReceive('syncOneById')
        ->once()
        ->andThrow(new QuickBooksException('QuickBooks API error.', 'body', 422));

    $processor = new QuickBooksWebhookProcessorService(
        $sync,
        app(TimeActivitySnapshotService::class),
    );

    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-1']);

    $job = new ProcessQuickBooksWebhookJob([
        'eventNotifications' => [
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '9', 'operation' => 'Update'],
                    ],
                ],
            ],
        ],
    ]);

    expect(fn () => $job->handle($processor))->toThrow(QuickBooksException::class);
});

it('soft-deletes snapshots when void webhook operation is received', function () {
    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-1']);
    $snapshot = TimeActivitySnapshot::factory()
        ->forRealm('realm-1')
        ->forEmployee('7')
        ->create(['qbo_id' => '55']);

    app(QuickBooksWebhookProcessorService::class)->process([
        'eventNotifications' => [
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '55', 'operation' => 'Void'],
                    ],
                ],
            ],
        ],
    ]);

    expect(TimeActivitySnapshot::query()->find($snapshot->id))->toBeNull();
});

it('validates webhook signatures with the verifier service', function () {
    $payload = '{"test":true}';
    $verifier = new QuickBooksWebhookVerifierService;

    config(['quickbooks.webhook_verifier' => 'secret']);
    $signature = base64_encode(hash_hmac('sha256', $payload, 'secret', true));

    expect($verifier->isValid($payload, $signature))->toBeTrue()
        ->and($verifier->isValid($payload, 'bad'))->toBeFalse();
});

it('ignores webhook notifications without entities or unknown realms', function () {
    Log::spy();

    app(QuickBooksWebhookProcessorService::class)->process([
        'eventNotifications' => [
            ['realmId' => 'missing-realm', 'dataChangeEvent' => ['entities' => [
                ['name' => 'TimeActivity', 'id' => '9', 'operation' => 'Update'],
            ]]],
            ['realmId' => 'realm-1', 'dataChangeEvent' => ['entities' => []]],
            ['realmId' => '', 'dataChangeEvent' => ['entities' => [
                ['name' => 'Customer', 'id' => '1', 'operation' => 'Update'],
            ]]],
        ],
    ]);

    Log::shouldHaveReceived('warning')->atLeast()->once();
});

it('skips non-time-activity webhook entities', function () {
    $user = User::factory()->create();
    QuickBooksToken::factory()->forUser($user)->create(['realm_id' => 'realm-1']);

    app(QuickBooksWebhookProcessorService::class)->process([
        'eventNotifications' => [
            [
                'realmId' => 'realm-1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'Customer', 'id' => '1', 'operation' => 'Update'],
                    ],
                ],
            ],
        ],
    ]);

    expect(TimeActivitySnapshot::query()->count())->toBe(0);
});
