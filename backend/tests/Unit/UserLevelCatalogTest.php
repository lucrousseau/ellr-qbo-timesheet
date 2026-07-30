<?php

use App\Support\UserLevelCatalog;

covers(UserLevelCatalog::class);

it('defines ordered permission tiers for seeding', function () {
    expect(UserLevelCatalog::definitions())->toBe([
        ['code' => 'employee', 'rank' => 0],
        ['code' => 'lead', 'rank' => 1],
        ['code' => 'manager', 'rank' => 2],
    ]);
});
