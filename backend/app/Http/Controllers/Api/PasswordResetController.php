<?php

/**
 * Password reset link and password update endpoints.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use App\Services\AuthSessionService;
use App\Services\PasswordResetLinkService;
use App\Support\UserLocaleApplicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Sends reset links and updates passwords from reset tokens.
 */
class PasswordResetController extends Controller
{
    /**
     * Injects password reset helpers.
     *
     * @param  AuthSessionService  $authSession  Session invalidation after password reset.
     * @param  PasswordResetLinkService  $resetLinks  Reset link delivery.
     */
    public function __construct(
        private readonly AuthSessionService $authSession,
        private readonly PasswordResetLinkService $resetLinks,
    ) {}

    /**
     * Sends a password reset link to the given email address.
     *
     * @param  ForgotPasswordRequest  $request  Validated forgot-password payload.
     * @return JsonResponse
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        UserLocaleApplicator::applyForEmail($validated['email']);

        $this->resetLinks->send(
            $validated['email'],
            $validated['client'] ?? null,
        );

        return response()->json(['message' => __('api.password_reset_sent')]);
    }

    /**
     * Resets the user password using a valid reset token.
     *
     * @param  ResetPasswordRequest  $request  Validated reset-password payload.
     * @return JsonResponse
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        UserLocaleApplicator::applyForEmail($validated['email'] ?? null);

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $this->authSession->invalidateUserSessions($user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => __('api.password_reset_failed')], 422); // @pest-mutate-ignore generic reset password failure response
        }

        return response()->json(['message' => __('api.password_reset_success')]);
    }
}
