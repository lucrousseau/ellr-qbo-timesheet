<?php

/**
 * Application service provider for container bindings and boot logic.
 */

namespace App\Providers;

use App\Services\UserLevelResolverService;
use App\Support\PasswordPolicy;
use App\Support\SqliteConnectionPragmas;
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
    public function register(): void
    {
        $this->app->singleton(UserLevelResolverService::class);
    }

    /**
     * Bootstraps services after registration.
     *
     * @return void
     */
    public function boot(): void
    {
        Password::defaults(fn (): Password => PasswordPolicy::rule());

        if (config('database.default') === 'sqlite') {
            $database = config('database.connections.sqlite.database');

            if ($database !== ':memory:' && (! is_string($database) || ! is_file($database))) {
                return;
            }

            $pdo = DB::connection('sqlite')->getPdo();
            $busyTimeout = (int) config('database.connections.sqlite.busy_timeout', 5000);
            $journalMode = config('database.connections.sqlite.journal_mode');

            SqliteConnectionPragmas::apply($pdo, $busyTimeout, $journalMode);
        }
    }
}
