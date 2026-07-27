<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registration of Laravel application services.
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
    public function boot(): void {}
}
