<?php

use App\Services\QuickBooksWebhookIngressService;
use Illuminate\Http\JsonResponse;

covers(QuickBooksWebhookIngressService::class);

beforeEach(function () {
    config([
        'quickbooks.webhook_max_payload_bytes' => 8192,
        'quickbooks.webhook_max_notifications' => 2,
        'quickbooks.webhook_max_entities_per_notification' => 2,
    ]);
});

it('rejects webhook payloads that exceed the byte limit', function () {
    config(['quickbooks.webhook_max_payload_bytes' => 100]);

    $result = app(QuickBooksWebhookIngressService::class)->decodeValidatedPayload(str_repeat('a', 101));

    expect($result)->toBeInstanceOf(JsonResponse::class)
        ->and($result->getStatusCode())->toBe(413);
});

it('rejects webhook payloads with invalid json', function () {
    $result = app(QuickBooksWebhookIngressService::class)->decodeValidatedPayload('{');

    expect($result)->toBeInstanceOf(JsonResponse::class)
        ->and($result->getStatusCode())->toBe(422);
});

it('rejects webhook payloads that exceed the notification limit', function () {
    $payload = json_encode([
        'eventNotifications' => [
            ['realmId' => '1'],
            ['realmId' => '2'],
            ['realmId' => '3'],
        ],
    ], JSON_THROW_ON_ERROR);

    $result = app(QuickBooksWebhookIngressService::class)->decodeValidatedPayload($payload);

    expect($result)->toBeInstanceOf(JsonResponse::class)
        ->and($result->getStatusCode())->toBe(422);
});

it('rejects webhook payloads that exceed the entity limit', function () {
    $payload = json_encode([
        'eventNotifications' => [
            [
                'realmId' => '1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '1'],
                        ['name' => 'TimeActivity', 'id' => '2'],
                        ['name' => 'TimeActivity', 'id' => '3'],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $result = app(QuickBooksWebhookIngressService::class)->decodeValidatedPayload($payload);

    expect($result)->toBeInstanceOf(JsonResponse::class)
        ->and($result->getStatusCode())->toBe(422);
});

it('rejects webhook payloads with malformed notifications', function () {
    $payload = json_encode([
        'eventNotifications' => [
            'not-an-array',
        ],
    ], JSON_THROW_ON_ERROR);

    $result = app(QuickBooksWebhookIngressService::class)->decodeValidatedPayload($payload);

    expect($result)->toBeInstanceOf(JsonResponse::class)
        ->and($result->getStatusCode())->toBe(422);
});

it('returns decoded payloads that pass ingress limits', function () {
    $payload = json_encode([
        'eventNotifications' => [
            [
                'realmId' => '1',
                'dataChangeEvent' => [
                    'entities' => [
                        ['name' => 'TimeActivity', 'id' => '9', 'operation' => 'Update'],
                    ],
                ],
            ],
        ],
    ], JSON_THROW_ON_ERROR);

    $result = app(QuickBooksWebhookIngressService::class)->decodeValidatedPayload($payload);

    expect($result)->toBeArray()
        ->and($result['eventNotifications'])->toHaveCount(1);
});
