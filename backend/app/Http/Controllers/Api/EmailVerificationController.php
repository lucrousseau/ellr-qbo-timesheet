<?php

/**
 * Email verification resend and signed-link confirmation endpoints.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sends verification emails and confirms signed verification links.
 */
class EmailVerificationController extends Controller
{
    /**
     * Redirects to the frontend after validating a signed verification link.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $id  User identifier.
     * @param  string  $hash  Email hash from the notification.
     * @return RedirectResponse
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $frontendUrl = rtrim((string) config('app.frontend_auth_url'), '/');
        $user = User::query()->find($id);

        if ($user === null || ! hash_equals($hash, sha1($user->getEmailForVerification()))) {
            return redirect("{$frontendUrl}?email=error&reason=invalid");
        }

        if (! $request->hasValidSignature()) {
            return redirect("{$frontendUrl}?email=error&reason=expired");
        }

        if ($user->hasVerifiedEmail()) {
            return redirect("{$frontendUrl}?email=already_verified");
        }

        $user->markEmailAsVerified();

        return redirect("{$frontendUrl}?email=verified");
    }

    /**
     * Queues a new verification email for the authenticated user.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => __('api.email_already_verified')]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => __('api.verification_link_sent')]);
    }
}
