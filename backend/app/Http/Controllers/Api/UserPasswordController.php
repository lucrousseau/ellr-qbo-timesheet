<?php

/**
 * Authenticated password change for signed-in users.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Services\AuthSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Updates the password for the authenticated user.
 */
class UserPasswordController extends Controller
{
    /**
     * Injects session invalidation helpers.
     *
     * @param  AuthSessionService  $authSession  Session revocation after password change.
     */
    public function __construct(
        private readonly AuthSessionService $authSession,
    ) {}

    /**
     * Changes the signed-in user password after validating the current password.
     *
     * @param  ChangePasswordRequest  $request  Validated change-password payload.
     * @return JsonResponse
     */
    public function update(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ]);
        $user->save();

        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $this->authSession->invalidateOtherUserSessions($user, $sessionId);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json(['message' => __('api.password_changed')]);
    }
}
