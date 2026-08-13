<?php

/**
 * Tests for accountant-facing QuickBooks sync group deep links.
 */

use App\Support\TimeEntrySyncGroupDetailUrl;

covers(TimeEntrySyncGroupDetailUrl::class);

it('builds an admin deep link with a trailing slash trimmed base', function () {
    config(['app.frontend_admin_url' => 'http://admin.test/']);

    expect(TimeEntrySyncGroupDetailUrl::forPublicId('abc-123'))
        ->toBe('http://admin.test/?sync_group=abc-123');
});

it('url-encodes the public identifier', function () {
    config(['app.frontend_admin_url' => 'http://admin.test']);

    expect(TimeEntrySyncGroupDetailUrl::forPublicId('a b/c'))
        ->toBe('http://admin.test/?sync_group=a%20b%2Fc');
});
