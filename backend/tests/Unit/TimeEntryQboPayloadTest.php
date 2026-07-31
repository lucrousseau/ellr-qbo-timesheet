<?php

use App\Models\TimeEntry;
use App\Models\User;
use App\Support\TimeEntryQboPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

covers(TimeEntryQboPayload::class);

it('maps stored refs and resolved labels to a quickbooks payload', function () {
    $entry = TimeEntry::factory()->forUser(User::factory()->create([
        'qbo_employee_ref' => '7',
    ]))->make([
        'customer_ref' => '11',
        'project_ref' => '22',
        'item_ref' => '33',
        'description' => 'Support',
        'is_billable' => true,
        'start_time' => '2026-07-27 09:00:00',
        'end_time' => '2026-07-27 17:00:00',
    ]);

    $payload = TimeEntryQboPayload::fromEntry($entry, [
        'customer_name' => 'Acme Corp',
        'project_name' => 'Website redesign',
        'item_name' => 'Consulting',
    ]);

    expect($payload)->toMatchArray([
        'customer_ref' => '11',
        'customer_name' => 'Acme Corp',
        'project_ref' => '22',
        'project_name' => 'Website redesign',
        'item_ref' => '33',
        'item_name' => 'Consulting',
        'description' => 'Support',
        'is_billable' => true,
    ]);
});

it('omits display labels when quickbooks names are unavailable', function () {
    $entry = TimeEntry::factory()->forUser(User::factory()->create([
        'qbo_employee_ref' => '7',
    ]))->make([
        'customer_ref' => '11',
        'start_time' => '2026-07-27 09:00:00',
        'end_time' => '2026-07-27 17:00:00',
    ]);

    $payload = TimeEntryQboPayload::fromEntry($entry);

    expect($payload)->toMatchArray([
        'customer_ref' => '11',
    ])->and($payload)->not->toHaveKey('customer_name');
});

it('omits empty display label strings from the quickbooks payload', function () {
    $entry = TimeEntry::factory()->forUser(User::factory()->create([
        'qbo_employee_ref' => '7',
    ]))->make([
        'customer_ref' => '11',
        'project_ref' => '22',
        'item_ref' => '33',
        'start_time' => '2026-07-27 09:00:00',
        'end_time' => '2026-07-27 17:00:00',
    ]);

    $payload = TimeEntryQboPayload::fromEntry($entry, [
        'customer_name' => '',
        'project_name' => '',
        'item_name' => null,
    ]);

    expect($payload)->toMatchArray([
        'customer_ref' => '11',
        'project_ref' => '22',
        'item_ref' => '33',
    ])->and($payload)->not->toHaveKeys(['customer_name', 'project_name', 'item_name']);
});
