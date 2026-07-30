<?php

use App\Support\TimezoneIdentifier;

covers(TimezoneIdentifier::class);

it('accepts valid iana timezone identifiers', function () {
    expect(TimezoneIdentifier::isValid('America/Toronto'))->toBeTrue()
        ->and(TimezoneIdentifier::normalize(' America/Toronto '))->toBe('America/Toronto');
});

it('rejects invalid timezone identifiers', function () {
    expect(TimezoneIdentifier::isValid('Not/A_Timezone'))->toBeFalse()
        ->and(TimezoneIdentifier::normalize('Not/A_Timezone'))->toBeNull()
        ->and(TimezoneIdentifier::normalize(''))->toBeNull();
});
