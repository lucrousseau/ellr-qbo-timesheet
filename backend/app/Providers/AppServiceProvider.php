<?php

/**
 * Application service provider for container bindings and boot logic.
 */

namespace App\Providers;

use App\Support\PasswordPolicy;
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
    }
}
