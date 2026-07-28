<?php

/**
 * Sanctum authentication endpoints: register, login, logout, and user profile.
 */

namespace App\Http\Controllers\Api;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\AuthSessionService;
use App\Support\UserApiResponse;
use App\Support\UserLocaleApplicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Validates credentials, manages Sanctum sessions, and returns JSON auth responses.
 */
class AuthController extends Controller
{
    /**
     * Injects the auth session helper.
     *
     * @param  AuthSessionService  $authSession  Login and registration session rules.
     */
    public function __construct(
        private readonly AuthSessionService $authSession,
    ) {}

    /**
     * Creates a user account if public registration is enabled.
     *
     * @param  RegisterRequest  $request  Validated registration payload.
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        if (! config('app.allow_registration')) {
            return response()->json([
                'message' => __('api.registration_disabled'),
                'error' => ApiErrorCode::RegistrationDisabled->value,
            ], 403);
        }

        $user = User::query()->create($request->validated());
        $this->authSession->sendVerificationIfRequired($user);
        Auth::login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json(['user' => UserApiResponse::resource($user)], 201);
    }

    /**
     * Authenticates a user and regenerates the session.
     *
     * @param  LoginRequest  $request  Validated login payload.
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials)) {
            UserLocaleApplicator::applyForEmail($credentials['email'] ?? null);

            return response()->json([
                'message' => __('api.invalid_credentials'),
                'error' => ApiErrorCode::InvalidCredentials->value,
            ], 401);
        }

        $user = $request->user();

        if ($response = $this->authSession->rejectUnverifiedLogin($user, $request)) {
            return $response;
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json(['user' => UserApiResponse::resource($request->user())]);
    }

    /**
     * Logs out the user and invalidates the session.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => __('api.logged_out')]);
    }

    /**
     * Returns the current authenticated user.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => UserApiResponse::resource($request->user())]);
    }
}
