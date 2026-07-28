<?php

use App\Support\QboEmployeeQuery;

covers(QboEmployeeQuery::class);

it('builds the active employee list query with a capped max results value', function () {
    expect(QboEmployeeQuery::listActive(25))->toBe(
        'SELECT Id, DisplayName, PrimaryEmailAddr FROM Employee WHERE Active = true MAXRESULTS 25',
    );
});

it('never allows zero max results in employee list queries', function () {
    expect(QboEmployeeQuery::listActive(0))->toContain('MAXRESULTS 1');
});
