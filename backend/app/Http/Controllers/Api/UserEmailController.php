<?php

/**
 * Authenticated email change for signed-in users.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeEmailRequest;
use App\Support\UserApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Updates the email address for the authenticated user.
 */
class UserEmailController extends Controller
{
    /**
     * Changes the signed-in user email after validating the current password.
     *
     * Always sends a verification link to the new address so the user can confirm ownership.
     *
     * @param  ChangeEmailRequest  $request  Validated change-email payload.
     * @return JsonResponse
     */
    public function update(ChangeEmailRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill([
            'email' => $request->validated('email'),
            'email_verified_at' => null,
        ]);
        $user->save();

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => __('api.email_changed'),
            'user' => UserApiResponse::resource($user->fresh()),
        ]);
    }
}
