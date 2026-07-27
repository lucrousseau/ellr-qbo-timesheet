<?php

/**
 * Restricts routes to users with the administrator flag.
 */

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aborts with 403 when the authenticated user is not an administrator.
 */
class EnsureUserIsAdmin
{
    /**
     * Verifies the signed-in user has administrator privileges.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Closure(Request): Response  $next  Next middleware.
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            abort(response()->json([
                'message' => 'Administrator access required.',
                'error' => ApiErrorCode::AdminRequired->value,
            ], 403));
        }

        return $next($request);
    }
}
