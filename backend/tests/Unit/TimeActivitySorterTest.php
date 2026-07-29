<?php

use App\Support\TimeActivitySorter;

covers(TimeActivitySorter::class);

it('sorts activities by start time descending', function () {
    $sorted = TimeActivitySorter::sortByRecent([
        ['Id' => '1', 'StartTime' => '2026-07-27T09:00:00'],
        ['Id' => '2', 'StartTime' => '2026-07-29T14:00:00'],
        ['Id' => '3', 'StartTime' => '2026-07-28T11:00:00'],
    ]);

    expect(array_column($sorted, 'Id'))->toBe(['2', '3', '1']);
});

it('falls back to transaction date when start time is missing', function () {
    $sorted = TimeActivitySorter::sortByRecent([
        (object) ['Id' => '1', 'TxnDate' => '2026-07-27'],
        (object) ['Id' => '2', 'TxnDate' => '2026-07-29'],
    ]);

    expect($sorted[0]->Id)->toBe('2');
});
