<?php

/**
 * Processes inbound QuickBooks webhook notifications.
 */

namespace App\Services;

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
     */
    public function __construct(
        private readonly TimeActivitySyncService $sync,
        private readonly TimeActivitySnapshotService $snapshots,
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
            Log::warning('QuickBooks webhook ignored invalid eventNotifications payload');

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
        $realmId = isset($notification['realmId']) ? (string) $notification['realmId'] : '';

        if ($realmId === '') {
            Log::warning('QuickBooks webhook ignored notification without realmId');

            return;
        }

        $entities = $notification['dataChangeEvent']['entities'] ?? [];

        if (! is_array($entities) || $entities === []) {
            Log::debug('QuickBooks webhook ignored notification without entities', [
                'realm_id' => $realmId,
            ]);

            return;
        }

        $token = QuickBooksToken::query()
            ->where('realm_id', $realmId)
            ->latest('id')
            ->first();

        if ($token === null) {
            Log::warning('QuickBooks webhook ignored unknown realm', [
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
        $name = isset($entity['name']) ? (string) $entity['name'] : '';

        if ($name !== 'TimeActivity') {
            Log::debug('QuickBooks webhook skipped non-time-activity entity', [
                'realm_id' => $token->realm_id,
                'entity' => $name,
            ]);

            return;
        }

        $id = isset($entity['id']) ? (string) $entity['id'] : '';
        $operation = strtolower(isset($entity['operation']) ? (string) $entity['operation'] : '');

        if ($id === '') {
            Log::warning('QuickBooks webhook ignored TimeActivity without id', [
                'realm_id' => $token->realm_id,
            ]);

            return;
        }

        if (in_array($operation, ['delete', 'void'], true)) {
            $this->snapshots->softDeleteByQboId($token->realm_id, $id);

            return;
        }

        $this->sync->syncOneById($token, $id, resolveMissingNames: false);
    }
}
