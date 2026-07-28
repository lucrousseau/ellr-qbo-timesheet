<?php

/**
 * Sets the Laravel application locale from the authenticated user preference.
 */

namespace App\Http\Middleware;

use App\Enums\UserLocale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the signed-in user locale to Laravel for validation and notifications.
 */
class SetLocaleFromUser
{
    /**
     * Resolves locale from the authenticated user or falls back to English.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Closure(Request): Response  $next  Next middleware.
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        App::setLocale($user !== null ? $user->preferredLocale() : UserLocale::En->value);

        return $next($request);
    }
}
