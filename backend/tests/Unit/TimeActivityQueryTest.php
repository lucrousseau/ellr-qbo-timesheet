<?php

use App\Support\TimeActivityQuery;

covers(TimeActivityQuery::class);

it('builds a paginated list query for an employee', function () {
    expect(TimeActivityQuery::listForEmployee('7', 1, 100))
        ->toBe("SELECT * FROM TimeActivity WHERE EmployeeRef = '7' STARTPOSITION 1 MAXRESULTS 100");
});

it('escapes single quotes in employee refs', function () {
    expect(TimeActivityQuery::escapeValue("7' OR '1'='1"))
        ->toBe("7\\' OR \\'1\\'=\\'1");
});
