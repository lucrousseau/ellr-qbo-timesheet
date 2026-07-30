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

it('tracks processed notifications separately per entity fingerprint', function () {
    Cache::flush();

    $service = app(QuickBooksWebhookIdempotencyService::class);
    $base = ['id' => '42', 'operation' => 'Update'];

    $service->markProcessed('realm-1', [...$base, 'lastUpdated' => '2026-07-30T12:00:00Z']);

    expect($service->wasProcessed('realm-1', [...$base, 'lastUpdated' => '2026-07-30T12:00:00Z']))->toBeTrue()
        ->and($service->wasProcessed('realm-1', [...$base, 'lastUpdated' => '2026-07-30T13:00:00Z']))->toBeFalse()
        ->and($service->wasProcessed('realm-2', [...$base, 'lastUpdated' => '2026-07-30T12:00:00Z']))->toBeFalse();
});

it('normalizes missing entity fields when building cache keys', function () {
    Cache::flush();

    $service = app(QuickBooksWebhookIdempotencyService::class);

    $service->markProcessed('realm-1', []);
    $service->markProcessed('realm-1', [
        'id' => '7',
        'operation' => 'DELETE',
        'lastUpdated' => '2026-07-30T12:00:00Z',
    ]);

    expect($service->wasProcessed('realm-1', []))->toBeTrue()
        ->and($service->wasProcessed('realm-1', [
            'id' => '7',
            'operation' => 'delete',
            'lastUpdated' => '2026-07-30T12:00:00Z',
        ]))->toBeTrue();
});
