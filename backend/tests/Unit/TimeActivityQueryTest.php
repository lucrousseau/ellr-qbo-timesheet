<?php

use App\Support\TimeActivityQuery;

covers(TimeActivityQuery::class);

it('builds a paginated list query within a transaction date window', function () {
    expect(TimeActivityQuery::listPage(1, 100, '2026-01-01'))
        ->toBe("SELECT * FROM TimeActivity WHERE TxnDate >= '2026-01-01' STARTPOSITION 1 MAXRESULTS 100");
});

it('escapes single quotes in transaction date filters', function () {
    expect(TimeActivityQuery::listPage(1, 10, "2026-01-01' OR '1'='1"))
        ->toBe("SELECT * FROM TimeActivity WHERE TxnDate >= '2026-01-01\\' OR \\'1\\'=\\'1' STARTPOSITION 1 MAXRESULTS 10");
});

it('escapes single quotes in employee refs', function () {
    expect(TimeActivityQuery::escapeValue("7' OR '1'='1"))
        ->toBe("7\\' OR \\'1\\'=\\'1");
});
