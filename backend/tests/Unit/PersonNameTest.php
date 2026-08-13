<?php

use App\Support\PersonName;

covers(PersonName::class);

it('splits and joins person names', function () {
    expect(PersonName::split('  Jane   Doe  '))->toBe(['Jane', 'Doe'])
        ->and(PersonName::split('Madonna'))->toBe(['Madonna', ''])
        ->and(PersonName::split(''))->toBe(['', ''])
        ->and(PersonName::join('Jane', 'Doe'))->toBe('Jane Doe')
        ->and(PersonName::join('Jane', ''))->toBe('Jane');
});

it('prefers QuickBooks given and family names when present', function () {
    expect(PersonName::fromQuickBooksEmployee((object) [
        'GivenName' => 'Jane',
        'FamilyName' => 'Doe',
        'DisplayName' => 'Jane Doe (ignored)',
    ]))->toBe([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'display_name' => 'Jane Doe',
    ]);
});

it('falls back to DisplayName when given and family names are missing', function () {
    expect(PersonName::fromQuickBooksEmployee((object) [
        'DisplayName' => 'Jane Doe',
    ]))->toBe([
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'display_name' => 'Jane Doe',
    ]);
});
