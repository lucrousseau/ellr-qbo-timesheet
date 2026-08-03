<?php

use App\Enums\TicketSource;
use App\Support\TicketFieldRules;
use Illuminate\Support\Facades\Validator;

covers(TicketFieldRules::class);
covers(TicketSource::class);

it('builds create rules for ticket fields', function () {
    $rules = TicketFieldRules::forCreate();

    expect($rules['ticket_key'])->toBe(['nullable', 'string', 'max:64'])
        ->and($rules['ticket_url'])->toBe(['nullable', 'url', 'max:2048'])
        ->and($rules['ticket_title'])->toBe(['nullable', 'string', 'max:512'])
        ->and($rules['ticket_source'][0])->toBe('nullable')
        ->and($rules['ticket_source'][1])->toBe('string')
        ->and((string) $rules['ticket_source'][2])->toBe('in:"manual","jira","linear"');
});

it('builds update rules with sometimes prefixes', function () {
    $rules = TicketFieldRules::forUpdate();

    expect($rules['ticket_key'])->toBe(['sometimes', 'nullable', 'string', 'max:64'])
        ->and($rules['ticket_url'])->toBe(['sometimes', 'nullable', 'url', 'max:2048'])
        ->and($rules['ticket_title'])->toBe(['sometimes', 'nullable', 'string', 'max:512'])
        ->and($rules['ticket_source'][0])->toBe('sometimes')
        ->and($rules['ticket_source'][1])->toBe('nullable')
        ->and($rules['ticket_source'][2])->toBe('string')
        ->and((string) $rules['ticket_source'][3])->toBe('in:"manual","jira","linear"');
});

it('validates known ticket sources', function () {
    expect(TicketSource::isValid('manual'))->toBeTrue()
        ->and(TicketSource::isValid('jira'))->toBeTrue()
        ->and(TicketSource::isValid('linear'))->toBeTrue()
        ->and(TicketSource::isValid('github'))->toBeFalse()
        ->and(TicketSource::isValid(null))->toBeFalse()
        ->and(TicketSource::isValid(''))->toBeFalse();
});

it('rejects invalid create ticket payloads through laravel validation', function () {
    $rules = TicketFieldRules::forCreate();

    expect(Validator::make(['ticket_key' => str_repeat('a', 65)], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['ticket_source' => 'github'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['ticket_url' => 'not-a-url'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['ticket_url' => 'https://example.com/'.str_repeat('a', 2048)], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['ticket_title' => str_repeat('a', 513)], $rules)->fails())->toBeTrue();

    foreach ([TicketSource::Manual->value, TicketSource::Jira->value, TicketSource::Linear->value] as $source) {
        expect(Validator::make([
            'ticket_key' => 'PROJ-1',
            'ticket_source' => $source,
            'ticket_url' => 'https://example.com/PROJ-1',
            'ticket_title' => 'Valid title',
        ], $rules)->passes())->toBeTrue();
    }

    expect(Validator::make([
        'ticket_key' => null,
        'ticket_source' => null,
        'ticket_url' => null,
        'ticket_title' => null,
    ], $rules)->passes())->toBeTrue();
});
