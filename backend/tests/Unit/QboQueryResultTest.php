<?php

use App\Support\QboQueryResult;

covers(QboQueryResult::class);

it('normalizes quickbooks query arrays', function () {
    $employee = (object) ['Id' => '1'];

    expect(QboQueryResult::entities([$employee]))->toBe([$employee]);
});

it('wraps a single quickbooks query entity in an array', function () {
    $employee = (object) ['Id' => '1'];

    expect(QboQueryResult::entities($employee))->toBe([$employee]);
});

it('returns an empty list for null quickbooks query results', function () {
    expect(QboQueryResult::entities(null))->toBe([])
        ->and(QboQueryResult::entities('invalid'))->toBe([]);
});

it('reindexes associative quickbooks query arrays', function () {
    $first = (object) ['Id' => '1'];
    $second = (object) ['Id' => '2'];

    expect(QboQueryResult::entities(['a' => $first, 'b' => $second]))->toBe([$first, $second]);
});
