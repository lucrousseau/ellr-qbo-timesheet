<?php

use App\Support\TimeActivityRefPayload;

covers(TimeActivityRefPayload::class);

it('builds customer refs from project selections when present', function () {
    expect(TimeActivityRefPayload::customerRef([
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website',
    ]))->toBe([
        'value' => '22',
        'name' => 'Website',
    ]);
});

it('omits customer and item refs when picker values are absent', function () {
    expect(TimeActivityRefPayload::customerRef([
        'customer_ref' => '',
        'project_ref' => null,
    ]))->toBeNull()
        ->and(TimeActivityRefPayload::itemRef([
            'item_ref' => null,
            'item_name' => 'Consulting',
        ]))->toBeNull();
});

it('builds customer refs from customer selections without a project', function () {
    expect(TimeActivityRefPayload::customerRef([
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
    ]))->toBe([
        'value' => '11',
        'name' => 'Acme Corp',
    ]);
});

it('builds item refs with optional display names', function () {
    expect(TimeActivityRefPayload::itemRef([
        'item_ref' => '33',
        'item_name' => 'Consulting',
    ]))->toBe([
        'value' => '33',
        'name' => 'Consulting',
    ]);
});
