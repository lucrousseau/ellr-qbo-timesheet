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
