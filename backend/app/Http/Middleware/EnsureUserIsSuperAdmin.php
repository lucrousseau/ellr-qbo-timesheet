<?php

/**
 * Restricts routes to platform super administrators.
 */

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aborts with 403 when the authenticated user is not a platform super administrator.
 */
class EnsureUserIsSuperAdmin
{
    /**
     * Verifies the signed-in user has platform super-administrator privileges.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Closure(Request): Response  $next  Next middleware.
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isSuperAdmin()) {
            abort(response()->json([
                'message' => __('api.super_admin_required'),
                'error' => ApiErrorCode::SuperAdminRequired->value,
            ], 403));
        }

        return $next($request);
    }
}
