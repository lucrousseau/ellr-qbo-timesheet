<?php

/**
 * Blocks authenticated users with unverified emails when verification is required.
 */

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies email verification only when REQUIRE_EMAIL_VERIFICATION is enabled.
 */
class EnsureEmailVerifiedIfRequired
{
    /**
     * Aborts with 403 when verification is required and the email is unverified.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Closure(Request): Response  $next  Next middleware.
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.require_email_verification')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            abort(response()->json([
                'message' => __('api.email_not_verified'),
                'error' => ApiErrorCode::EmailNotVerified->value,
            ], 403));
        }

        return $next($request);
    }
}
