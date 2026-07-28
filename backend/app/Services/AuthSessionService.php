<?php

/**
 * Session helpers for login and registration flows.
 */

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Applies email verification rules during authentication.
 */
class AuthSessionService
{
    /**
     * Logs out and returns a JSON response when verification is required but pending.
     *
     * @param  User  $user  Authenticated user.
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse|null
     */
    public function rejectUnverifiedLogin(User $user, Request $request): ?JsonResponse
    {
        if (! config('app.require_email_verification') || $user->hasVerifiedEmail()) {
            return null;
        }

        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => __('api.email_not_verified'),
            'error' => ApiErrorCode::EmailNotVerified->value,
        ], 403);
    }

    /**
     * Sends a verification email when required for new accounts.
     *
     * @param  User  $user  Newly registered user.
     * @return void
     */
    public function sendVerificationIfRequired(User $user): void
    {
        if (config('app.require_email_verification')) {
            $user->sendEmailVerificationNotification();
        }
    }

    /**
     * Revokes persisted sessions and API tokens for the given user.
     *
     * @param  User  $user  Account whose sessions should be invalidated.
     * @return void
     */
    public function invalidateUserSessions(User $user): void
    {
        $this->invalidateOtherUserSessions($user);
    }

    /**
     * Revokes API tokens and database sessions except an optional active session id.
     *
     * @param  User  $user  Account whose sessions should be invalidated.
     * @param  string|null  $exceptSessionId  Session id to keep (current browser session).
     * @return void
     */
    public function invalidateOtherUserSessions(User $user, ?string $exceptSessionId = null): void
    {
        if (config('session.driver') === 'database') {
            $query = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id);

            if ($exceptSessionId !== null) {
                $query->where('id', '!=', $exceptSessionId);
            }

            $query->delete();
        }

        $user->tokens()->delete();
    }
}
