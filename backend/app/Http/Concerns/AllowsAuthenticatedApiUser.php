<?php

/**
 * Shared authorize() for form requests behind auth:sanctum middleware.
 */

namespace App\Http\Concerns;

/**
 * Delegates authorization to upstream Sanctum middleware on protected API routes.
 */
trait AllowsAuthenticatedApiUser
{
    /**
     * Allows any authenticated user (Sanctum check runs upstream).
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }
}
