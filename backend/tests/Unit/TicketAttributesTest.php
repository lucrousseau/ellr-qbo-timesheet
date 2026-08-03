<?php

use App\Enums\TicketSource;
use App\Support\TicketAttributes;

covers(TicketAttributes::class);

it('defaults ticket source to manual when a key is present', function () {
    expect(TicketAttributes::fromValidated([
        'ticket_key' => '  PROJ-42 ',
        'ticket_url' => 'https://example.com/PROJ-42',
        'ticket_title' => ' Fix auth ',
    ]))->toBe([
        'ticket_key' => 'PROJ-42',
        'ticket_source' => TicketSource::Manual->value,
        'ticket_url' => 'https://example.com/PROJ-42',
        'ticket_title' => 'Fix auth',
    ]);
});

it('preserves an explicit jira ticket source when a key is present', function () {
    expect(TicketAttributes::fromValidated([
        'ticket_key' => 'PROJ-42',
        'ticket_source' => TicketSource::Jira->value,
        'ticket_url' => ' https://jira.example/browse/PROJ-42 ',
        'ticket_title' => ' Auth fix ',
    ]))->toBe([
        'ticket_key' => 'PROJ-42',
        'ticket_source' => TicketSource::Jira->value,
        'ticket_url' => 'https://jira.example/browse/PROJ-42',
        'ticket_title' => 'Auth fix',
    ]);
});

it('defaults empty ticket source strings to manual when a key is present', function () {
    expect(TicketAttributes::fromValidated([
        'ticket_key' => 'PROJ-42',
        'ticket_source' => '',
    ]))->toBe([
        'ticket_key' => 'PROJ-42',
        'ticket_source' => TicketSource::Manual->value,
        'ticket_url' => null,
        'ticket_title' => null,
    ]);
});

it('clears ticket metadata when the key is empty', function () {
    expect(TicketAttributes::fromValidated([
        'ticket_key' => '',
        'ticket_source' => TicketSource::Jira->value,
        'ticket_url' => 'https://example.com/PROJ-42',
        'ticket_title' => 'Fix auth',
    ]))->toBe([
        'ticket_key' => null,
        'ticket_source' => null,
        'ticket_url' => null,
        'ticket_title' => null,
    ]);
});

it('clears ticket metadata when the key is not a string', function () {
    expect(TicketAttributes::fromValidated([
        'ticket_key' => 42,
        'ticket_source' => TicketSource::Linear->value,
    ]))->toBe([
        'ticket_key' => null,
        'ticket_source' => null,
        'ticket_url' => null,
        'ticket_title' => null,
    ]);
});

it('maps partial ticket metadata updates without clearing the key', function () {
    expect(TicketAttributes::fromPartialValidated([
        'ticket_title' => ' Updated title ',
        'ticket_url' => ' https://example.com/x ',
        'ticket_source' => TicketSource::Jira->value,
    ]))->toBe([
        'ticket_source' => TicketSource::Jira->value,
        'ticket_url' => 'https://example.com/x',
        'ticket_title' => 'Updated title',
    ]);
});

it('clears all ticket fields when a partial update empties the key', function () {
    expect(TicketAttributes::fromPartialValidated([
        'ticket_key' => ' ',
        'ticket_source' => TicketSource::Linear->value,
    ]))->toBe([
        'ticket_key' => null,
        'ticket_source' => null,
        'ticket_url' => null,
        'ticket_title' => null,
    ]);
});

it('ignores invalid ticket source values', function () {
    expect(TicketAttributes::fromValidated([
        'ticket_key' => 'PROJ-1',
        'ticket_source' => 'github',
        'ticket_url' => 12,
        'ticket_title' => ['x'],
    ]))->toBe([
        'ticket_key' => 'PROJ-1',
        'ticket_source' => TicketSource::Manual->value,
        'ticket_url' => null,
        'ticket_title' => null,
    ]);
});

it('nullifies non-string and blank partial ticket source values', function () {
    expect(TicketAttributes::fromPartialValidated([
        'ticket_source' => 12,
    ]))->toBe([
        'ticket_source' => null,
    ])->and(TicketAttributes::fromPartialValidated([
        'ticket_source' => '',
    ]))->toBe([
        'ticket_source' => null,
    ]);
});

it('nullifies blank optional ticket strings on partial updates', function () {
    expect(TicketAttributes::fromPartialValidated([
        'ticket_url' => '   ',
        'ticket_title' => "\t",
    ]))->toBe([
        'ticket_url' => null,
        'ticket_title' => null,
    ]);
});

it('returns an empty array when no ticket fields are present', function () {
    expect(TicketAttributes::fromPartialValidated([
        'description' => 'Notes',
    ]))->toBe([]);
});
