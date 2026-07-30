<?php

/**
 * Ensures authenticated API users belong to a tenant organization.
 */

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aborts when a signed-in user has no organization_id.
 */
class EnsureUserHasOrganization
{
    /**
     * Rejects authenticated requests from accounts without a tenant organization.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Closure(Request): Response  $next  Next middleware handler.
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->organization_id === null) {
            abort(response()->json([
                'message' => __('api.organization_required'),
                'error' => ApiErrorCode::OrganizationRequired->value,
            ], 403));
        }

        return $next($request);
    }
}
