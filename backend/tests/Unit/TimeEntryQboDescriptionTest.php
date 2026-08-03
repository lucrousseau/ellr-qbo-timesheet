<?php

use App\Support\TimeEntryQboDescription;

covers(TimeEntryQboDescription::class);

it('composes ticket key and description for quickbooks', function () {
    expect(TimeEntryQboDescription::compose('PROJ-12', 'Fix login'))->toBe('[PROJ-12] Fix login');
});

it('returns only the ticket key when description is empty', function () {
    expect(TimeEntryQboDescription::compose('PROJ-12', '  '))->toBe('[PROJ-12]');
});

it('returns only the description when ticket key is empty', function () {
    expect(TimeEntryQboDescription::compose(null, 'Fix login'))->toBe('Fix login');
});

it('returns null when both values are empty', function () {
    expect(TimeEntryQboDescription::compose(' ', null))->toBeNull();
});
