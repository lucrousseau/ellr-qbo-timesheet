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

it('maps partial ticket metadata updates without clearing the key', function () {
    expect(TicketAttributes::fromPartialValidated([
        'ticket_title' => ' Updated title ',
    ]))->toBe([
        'ticket_title' => 'Updated title',
    ]);
});

it('returns an empty array when no ticket fields are present', function () {
    expect(TicketAttributes::fromPartialValidated([
        'description' => 'Notes',
    ]))->toBe([]);
});
