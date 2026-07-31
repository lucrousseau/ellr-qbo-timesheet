<?php

use App\Support\QboRefNormalizer;

covers(QboRefNormalizer::class);

it('normalizes quickbooks identifiers to numeric strings', function () {
    expect(QboRefNormalizer::normalize('11'))->toBe('11')
        ->and(QboRefNormalizer::normalize('Customer-11'))->toBe('11')
        ->and(QboRefNormalizer::normalize(''))->toBeNull();
});

it('matches quickbooks identifiers with different formatting', function () {
    expect(QboRefNormalizer::refsMatch('11', 'Customer-11'))->toBeTrue()
        ->and(QboRefNormalizer::refsMatch('11', '12'))->toBeFalse();
});

it('finds picker options using normalized identifiers', function () {
    $options = [
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ];

    expect(QboRefNormalizer::optionExists($options, 'Customer-11'))->toBeTrue()
        ->and(QboRefNormalizer::optionExists($options, '99'))->toBeFalse();
});

it('returns null for missing or non-numeric quickbooks identifiers', function () {
    expect(QboRefNormalizer::normalize(null))->toBeNull()
        ->and(QboRefNormalizer::normalize('abc'))->toBeNull()
        ->and(QboRefNormalizer::optionExists([], null))->toBeFalse()
        ->and(QboRefNormalizer::refsMatch(null, null))->toBeTrue();
});

it('matches picker options when quickbooks returns numeric ids', function () {
    $options = [
        ['id' => 11, 'display_name' => 'Acme Corp'],
    ];

    expect(QboRefNormalizer::optionExists($options, '11'))->toBeTrue();
});

it('preserves zero as a valid quickbooks identifier', function () {
    expect(QboRefNormalizer::normalize('0'))->toBe('0');
});

it('resolves display names from picker options using normalized identifiers', function () {
    $options = [
        ['id' => '11', 'display_name' => 'Acme Corp'],
    ];

    expect(QboRefNormalizer::displayNameForRef($options, 'Customer-11'))->toBe('Acme Corp')
        ->and(QboRefNormalizer::displayNameForRef($options, '99'))->toBeNull();
});

it('returns null when picker display names are blank after trimming', function () {
    $options = [
        ['id' => '11', 'display_name' => '   '],
    ];

    expect(QboRefNormalizer::displayNameForRef($options, '11'))->toBeNull();
});
