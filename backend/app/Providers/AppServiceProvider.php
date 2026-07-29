<?php

/**
 * Application service provider for container bindings and boot logic.
 */

namespace App\Providers;

use App\Support\PasswordPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

/**
 * Registers application-level container bindings on boot.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Registers container IoC bindings.
     *
     * @return void
     */
    public function register(): void {}

    /**
     * Bootstraps services after registration.
     *
     * @return void
     */
    public function boot(): void
    {
        Password::defaults(fn (): Password => PasswordPolicy::rule());

        if (config('database.default') === 'sqlite') {
            $pdo = DB::connection('sqlite')->getPdo();
            $busyTimeout = (int) config('database.connections.sqlite.busy_timeout', 5000);

            if ($busyTimeout > 0) {
                $pdo->exec('PRAGMA busy_timeout = '.$busyTimeout);
            }

            $journalMode = config('database.connections.sqlite.journal_mode');

            if (is_string($journalMode) && $journalMode !== '') {
                $pdo->exec('PRAGMA journal_mode = '.$journalMode);
            }
        }
    }
}
