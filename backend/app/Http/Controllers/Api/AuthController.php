<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Sanctum authentication: registration, login, session, and user profile.
 */
class AuthController extends Controller
{
    /**
     * Creates a user account if public registration is enabled.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        if (! config('app.allow_registration')) {
            return response()->json([
                'message' => 'Registration disabled.',
                'error' => ApiErrorCode::RegistrationDisabled->value,
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json(['user' => $user], 201);
    }

    /**
     * Authenticates a user and regenerates the session.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json(['user' => $request->user()]);
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

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Returns the current authenticated user.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }

    /**
     * Associates a QuickBooks employee with the user account.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function updateQboEmployee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qbo_employee_ref' => ['required', 'string', 'max:255'],
            'qbo_employee_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json(['user' => $user->fresh()]);
    }
}
