<?php

use App\Providers\AppServiceProvider;
use App\Services\UserLevelResolverService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

covers(AppServiceProvider::class);

it('registers UserLevelResolverService as a singleton', function () {
    $provider = new AppServiceProvider(app());
    $provider->register();

    expect(app(UserLevelResolverService::class))
        ->toBe(app(UserLevelResolverService::class));
});

it('configures password validation defaults from PasswordPolicy', function () {
    (new AppServiceProvider(app()))->boot();

    expect(Password::default())->toBeInstanceOf(Password::class);
});

it('skips sqlite pragma setup when the database file is missing', function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => database_path('missing-behat-test.sqlite'),
    ]);
    DB::purge('sqlite');

    (new AppServiceProvider(app()))->boot();

    expect(true)->toBeTrue();
});

it('skips sqlite pragma setup when the sqlite database path is not a string', function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => null,
    ]);
    DB::purge('sqlite');

    (new AppServiceProvider(app()))->boot();

    expect(true)->toBeTrue();
});

it('applies sqlite pragmas for in-memory databases', function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'database.connections.sqlite.busy_timeout' => 5000,
        'database.connections.sqlite.journal_mode' => 'wal',
    ]);
    DB::purge('sqlite');
    DB::reconnect('sqlite');

    config(['database.connections.sqlite.busy_timeout' => 7500]);

    (new AppServiceProvider(app()))->boot();

    $pdo = DB::connection('sqlite')->getPdo();

    expect((int) $pdo->query('PRAGMA busy_timeout')->fetchColumn())->toBe(7500);
});

it('applies sqlite pragmas when the database file exists on disk', function () {
    $path = database_path('app-provider-mutation-test.sqlite');
    if (is_file($path)) {
        unlink($path);
    }
    touch($path);

    try {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $path,
            'database.connections.sqlite.busy_timeout' => 4000,
            'database.connections.sqlite.journal_mode' => 'delete',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        (new AppServiceProvider(app()))->boot();

        $pdo = DB::connection('sqlite')->getPdo();

        expect((int) $pdo->query('PRAGMA busy_timeout')->fetchColumn())->toBe(4000)
            ->and(strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn()))->toBe('delete');
    } finally {
        DB::disconnect('sqlite');
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('applies journal mode pragma from boot when it differs from the connector default', function () {
    $path = database_path('app-provider-journal-test.sqlite');
    if (is_file($path)) {
        unlink($path);
    }
    touch($path);

    try {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $path,
            'database.connections.sqlite.busy_timeout' => 0,
            'database.connections.sqlite.journal_mode' => 'wal',
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        config(['database.connections.sqlite.journal_mode' => 'delete']);

        (new AppServiceProvider(app()))->boot();

        $pdo = DB::connection('sqlite')->getPdo();

        expect(strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn()))->toBe('delete');
    } finally {
        DB::disconnect('sqlite');
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('skips busy timeout pragma when configured to zero', function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'database.connections.sqlite.busy_timeout' => 0,
        'database.connections.sqlite.journal_mode' => 'wal',
    ]);
    DB::purge('sqlite');
    DB::reconnect('sqlite');

    (new AppServiceProvider(app()))->boot();

    $pdo = DB::connection('sqlite')->getPdo();

    expect((int) $pdo->query('PRAGMA busy_timeout')->fetchColumn())->toBe(0);
});

it('skips journal mode pragma when journal_mode is empty', function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'database.connections.sqlite.busy_timeout' => 7500,
        'database.connections.sqlite.journal_mode' => 'wal',
    ]);
    DB::purge('sqlite');
    DB::reconnect('sqlite');

    config(['database.connections.sqlite.journal_mode' => '']);

    (new AppServiceProvider(app()))->boot();

    expect((int) DB::connection('sqlite')->getPdo()->query('PRAGMA busy_timeout')->fetchColumn())->toBe(7500);
});

it('does not configure sqlite when the default connection is not sqlite', function () {
    config(['database.default' => 'mysql']);

    (new AppServiceProvider(app()))->boot();

    expect(config('database.default'))->toBe('mysql');
});
