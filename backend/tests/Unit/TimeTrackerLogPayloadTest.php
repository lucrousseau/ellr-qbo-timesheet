<?php

use App\Models\ActiveTimeSession;
use App\Support\TimeTrackerLogPayload;
use Carbon\CarbonImmutable;

covers(TimeTrackerLogPayload::class);

it('maps session fields to a quickbooks time activity payload', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    $session = new ActiveTimeSession([
        'customer_ref' => '11',
        'project_ref' => '22',
        'service_ref' => '33',
        'description' => 'Support tickets',
        'ticket_key' => 'ELLR-1',
        'ticket_source' => 'manual',
        'ticket_url' => 'https://example.com/ELLR-1',
        'ticket_title' => 'Timer ticket',
        'is_billable' => true,
    ]);

    $payload = TimeTrackerLogPayload::forSession($session, 90);

    expect($payload)->toMatchArray([
        'start_time' => '2026-07-28T11:58:30+00:00',
        'end_time' => '2026-07-28T12:00:00+00:00',
        'is_billable' => true,
        'description' => 'Support tickets',
        'ticket_key' => 'ELLR-1',
        'ticket_source' => 'manual',
        'ticket_url' => 'https://example.com/ELLR-1',
        'ticket_title' => 'Timer ticket',
        'customer_ref' => '11',
        'project_ref' => '22',
        'item_ref' => '33',
    ]);

    CarbonImmutable::setTestNow();
});

it('omits optional fields when session values are empty', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    $session = new ActiveTimeSession([
        'customer_ref' => null,
        'project_ref' => null,
        'service_ref' => null,
        'description' => '',
        'is_billable' => false,
    ]);

    $payload = TimeTrackerLogPayload::forSession($session, 60);

    expect($payload)->toBe([
        'start_time' => '2026-07-28T11:59:00+00:00',
        'end_time' => '2026-07-28T12:00:00+00:00',
        'is_billable' => false,
    ]);

    CarbonImmutable::setTestNow();
});

it('includes customer ref without name when only the ref is set', function () {
    CarbonImmutable::setTestNow('2026-07-28 12:00:00');

    $session = new ActiveTimeSession([
        'customer_ref' => '11',
        'project_ref' => null,
        'service_ref' => null,
        'description' => null,
        'is_billable' => false,
    ]);

    $payload = TimeTrackerLogPayload::forSession($session, 60);

    expect($payload)->toMatchArray([
        'customer_ref' => '11',
        'is_billable' => false,
    ])->and($payload)->not->toHaveKey('customer_name');

    CarbonImmutable::setTestNow();
});
