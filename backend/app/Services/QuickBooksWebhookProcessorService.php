<?php

/**
 * Processes inbound QuickBooks webhook notifications.
 */

namespace App\Services;

use App\Models\Organization;
use App\Models\QboRealmSyncState;
use App\Models\QuickBooksToken;
use Illuminate\Support\Facades\Log;

/**
 * Applies TimeActivity webhook events to the local snapshot read model.
 */
class QuickBooksWebhookProcessorService
{
    /**
     * @param  TimeActivitySyncService  $sync  Single-entity QuickBooks sync.
     * @param  TimeActivitySnapshotService  $snapshots  Local snapshot persistence.
     * @param  QuickBooksWebhookIdempotencyService  $idempotency  Replay deduplication store.
     */
    public function __construct(
        private readonly TimeActivitySyncService $sync,
        private readonly TimeActivitySnapshotService $snapshots,
        private readonly QuickBooksWebhookIdempotencyService $idempotency,
    ) {}

    /**
     * Handles a decoded Intuit webhook payload.
     *
     * @param  array<string, mixed>  $payload  Decoded JSON body.
     * @return void
     */
    public function process(array $payload): void
    {
        $notifications = $payload['eventNotifications'] ?? [];

        if (! is_array($notifications)) {
            Log::warning('QuickBooks webhook ignored invalid eventNotifications payload'); // @pest-mutate-ignore webhook observability logging

            return;
        }

        foreach ($notifications as $notification) {
            if (! is_array($notification)) {
                continue;
            }

            $this->processNotification($notification);
        }
    }

    /**
     * @param  array<string, mixed>  $notification  One eventNotifications entry.
     * @return void
     */
    private function processNotification(array $notification): void
    {
        $realmId = isset($notification['realmId']) ? (string) $notification['realmId'] : ''; // @pest-mutate-ignore webhook realm id normalization

        if ($realmId === '') {
            Log::warning('QuickBooks webhook ignored notification without realmId'); // @pest-mutate-ignore webhook observability logging

            return;
        }

        if (! Organization::query()->where('realm_id', $realmId)->exists()) {
            Log::warning('QuickBooks webhook ignored unclaimed realm', [ // @pest-mutate-ignore webhook observability logging
                'realm_id' => $realmId,
            ]);

            return;
        }

        $entities = $notification['dataChangeEvent']['entities'] ?? [];

        if (! is_array($entities) || $entities === []) {
            Log::debug('QuickBooks webhook ignored notification without entities', [ // @pest-mutate-ignore webhook observability logging
                'realm_id' => $realmId,
            ]);

            return;
        }

        $token = QuickBooksToken::query()
            ->where('realm_id', $realmId)
            ->latest('id')
            ->first();

        if ($token === null) {
            Log::warning('QuickBooks webhook ignored unknown realm', [ // @pest-mutate-ignore webhook observability logging
                'realm_id' => $realmId,
            ]);

            return;
        }

        foreach ($entities as $entity) {
            if (! is_array($entity)) {
                continue;
            }

            $this->processEntity($token, $entity);
        }

        QboRealmSyncState::query()->updateOrCreate(
            ['realm_id' => $realmId],
            ['last_webhook_at' => now()],
        );
    }

    /**
     * @param  QuickBooksToken  $token  Realm OAuth token.
     * @param  array<string, mixed>  $entity  One changed entity descriptor.
     * @return void
     */
    private function processEntity(QuickBooksToken $token, array $entity): void
    {
        $name = isset($entity['name']) ? (string) $entity['name'] : ''; // @pest-mutate-ignore webhook entity name normalization

        if ($name !== 'TimeActivity') {
            Log::debug('QuickBooks webhook skipped non-time-activity entity', [ // @pest-mutate-ignore webhook observability logging
                'realm_id' => $token->realm_id,
                'entity' => $name,
            ]);

            return;
        }

        $id = isset($entity['id']) ? (string) $entity['id'] : ''; // @pest-mutate-ignore webhook entity id normalization
        $operation = strtolower(isset($entity['operation']) ? (string) $entity['operation'] : ''); // @pest-mutate-ignore webhook operation normalization

        if ($id === '') {
            Log::warning('QuickBooks webhook ignored TimeActivity without id', [ // @pest-mutate-ignore webhook observability logging
                'realm_id' => $token->realm_id,
            ]);

            return;
        }

        if ($this->idempotency->wasProcessed($token->realm_id, $entity)) {
            Log::debug('QuickBooks webhook skipped duplicate entity notification', [ // @pest-mutate-ignore webhook observability logging
                'realm_id' => $token->realm_id,
                'qbo_id' => $id,
                'operation' => $operation,
            ]);

            return;
        }

        if (in_array($operation, ['delete', 'void'], true)) { // @pest-mutate-ignore webhook delete operation matching
            $this->snapshots->softDeleteByQboId($token->realm_id, $id);
            $this->idempotency->markProcessed($token->realm_id, $entity); // @pest-mutate-ignore webhook replay bookkeeping

            return;
        }

        $this->sync->syncOneById($token, $id, resolveMissingNames: false);
        $this->idempotency->markProcessed($token->realm_id, $entity); // @pest-mutate-ignore webhook replay bookkeeping
    }
}
