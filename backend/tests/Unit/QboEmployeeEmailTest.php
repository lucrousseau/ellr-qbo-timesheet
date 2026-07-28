<?php

use App\Support\QboEmployeeEmail;

covers(QboEmployeeEmail::class);

it('reads employee email from a nested quickbooks email object', function () {
    $employee = (object) [
        'PrimaryEmailAddr' => (object) ['Address' => 'jane@example.com'],
    ];

    expect(QboEmployeeEmail::fromEmployee($employee))->toBe('jane@example.com');
});

it('reads employee email from a quickbooks email string', function () {
    $employee = (object) [
        'PrimaryEmailAddr' => 'jane@example.com',
    ];

    expect(QboEmployeeEmail::fromEmployee($employee))->toBe('jane@example.com');
});

it('reads employee email from a quickbooks email array', function () {
    $employee = (object) [
        'PrimaryEmailAddr' => ['Address' => 'jane@example.com'],
    ];

    expect(QboEmployeeEmail::fromEmployee($employee))->toBe('jane@example.com');
});

it('returns null when quickbooks employee email is missing', function () {
    $employee = (object) [
        'PrimaryEmailAddr' => (object) ['Address' => ''],
    ];

    expect(QboEmployeeEmail::fromEmployee($employee))->toBeNull();
});

it('returns null when the employee has no primary email field', function () {
    expect(QboEmployeeEmail::fromEmployee((object) []))->toBeNull();
});

it('returns null when quickbooks email values are only whitespace', function () {
    expect(QboEmployeeEmail::normalize('   '))->toBeNull();
});

it('returns null when quickbooks email address values are not strings', function () {
    expect(QboEmployeeEmail::normalize(['Address' => 42]))->toBeNull();
});

it('trims quickbooks email address values', function () {
    expect(QboEmployeeEmail::normalize(['Address' => '  jane@example.com  ']))->toBe('jane@example.com');
});
