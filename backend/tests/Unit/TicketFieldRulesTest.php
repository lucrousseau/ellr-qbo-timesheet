<?php

use App\Enums\TicketSource;
use App\Support\TicketFieldRules;

covers(TicketFieldRules::class);
covers(TicketSource::class);

it('builds create rules for ticket fields', function () {
    $rules = TicketFieldRules::forCreate();

    expect($rules)->toHaveKeys(['ticket_key', 'ticket_source', 'ticket_url', 'ticket_title'])
        ->and($rules['ticket_key'])->toContain('nullable')
        ->and($rules['ticket_key'])->not->toContain('sometimes');
});

it('builds update rules with sometimes prefixes', function () {
    $rules = TicketFieldRules::forUpdate();

    expect($rules['ticket_key'])->toContain('sometimes')
        ->and($rules['ticket_source'])->toContain('sometimes');
});

it('validates known ticket sources', function () {
    expect(TicketSource::isValid('manual'))->toBeTrue()
        ->and(TicketSource::isValid('jira'))->toBeTrue()
        ->and(TicketSource::isValid('linear'))->toBeTrue()
        ->and(TicketSource::isValid('github'))->toBeFalse()
        ->and(TicketSource::isValid(null))->toBeFalse()
        ->and(TicketSource::isValid(''))->toBeFalse();
});
