<?php

/**
 * Tests for QuickBooks webhook replay deduplication.
 */

use App\Services\QuickBooksWebhookIdempotencyService;
use Illuminate\Support\Facades\Cache;

covers(QuickBooksWebhookIdempotencyService::class);

it('treats the first entity notification as unprocessed', function () {
    Cache::flush();

    $service = app(QuickBooksWebhookIdempotencyService::class);

    expect($service->wasProcessed('realm-1', [
        'id' => '42',
        'operation' => 'Update',
        'lastUpdated' => '2026-07-30T12:00:00Z',
    ]))->toBeFalse();
});

it('ignores replayed entity notifications within the ttl window', function () {
    Cache::flush();

    $service = app(QuickBooksWebhookIdempotencyService::class);
    $entity = [
        'id' => '42',
        'operation' => 'Update',
        'lastUpdated' => '2026-07-30T12:00:00Z',
    ];

    expect($service->wasProcessed('realm-1', $entity))->toBeFalse();

    $service->markProcessed('realm-1', $entity);

    expect($service->wasProcessed('realm-1', $entity))->toBeTrue();
});

it('does not mark failed notifications as processed until explicitly recorded', function () {
    Cache::flush();

    $service = app(QuickBooksWebhookIdempotencyService::class);
    $entity = [
        'id' => '42',
        'operation' => 'Update',
        'lastUpdated' => '2026-07-30T12:00:00Z',
    ];

    expect($service->wasProcessed('realm-1', $entity))->toBeFalse()
        ->and($service->wasProcessed('realm-1', $entity))->toBeFalse();
});
