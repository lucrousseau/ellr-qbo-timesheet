<?php

use App\Support\SqliteConnectionPragmas;

covers(SqliteConnectionPragmas::class);

it('applies busy timeout and journal mode pragmas', function () {
    $path = database_path('sqlite-connection-pragmas-test.sqlite');
    if (is_file($path)) {
        unlink($path);
    }
    touch($path);

    try {
        $pdo = new PDO('sqlite:'.$path);

        SqliteConnectionPragmas::apply($pdo, 7500, 'delete');

        expect((int) $pdo->query('PRAGMA busy_timeout')->fetchColumn())->toBe(7500)
            ->and(strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn()))->toBe('delete');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('skips busy timeout pragma when configured to zero', function () {
    $pdo = new PDO('sqlite::memory:');

    SqliteConnectionPragmas::apply($pdo, 5000, null);
    SqliteConnectionPragmas::apply($pdo, 0, null);

    expect((int) $pdo->query('PRAGMA busy_timeout')->fetchColumn())->toBe(5000);
});

it('skips journal mode pragma when journal mode is empty', function () {
    $pdo = new PDO('sqlite::memory:');

    SqliteConnectionPragmas::apply($pdo, 5000, '');

    expect((int) $pdo->query('PRAGMA busy_timeout')->fetchColumn())->toBe(5000)
        ->and(strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn()))->toBe('memory');
});

it('skips journal mode pragma when journal mode is not a string', function () {
    $pdo = new PDO('sqlite::memory:');

    SqliteConnectionPragmas::apply($pdo, 5000, null);

    expect(strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn()))->toBe('memory');
});

it('applies the minimum positive busy timeout pragma', function () {
    $pdo = new PDO('sqlite::memory:');

    SqliteConnectionPragmas::apply($pdo, 1, null);

    expect((int) $pdo->query('PRAGMA busy_timeout')->fetchColumn())->toBe(1);
});

it('applies wal journal mode pragmas on file databases', function () {
    $path = database_path('sqlite-wal-test.sqlite');
    if (is_file($path)) {
        unlink($path);
    }
    touch($path);

    try {
        $pdo = new PDO('sqlite:'.$path);

        SqliteConnectionPragmas::apply($pdo, 2500, 'wal');

        expect((int) $pdo->query('PRAGMA busy_timeout')->fetchColumn())->toBe(2500)
            ->and(strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn()))->toBe('wal');
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});
