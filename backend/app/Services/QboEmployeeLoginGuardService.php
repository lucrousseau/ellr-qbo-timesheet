<?php

/**
 * Rejects timesheet logins when the mapped QuickBooks employee no longer exists.
 */

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Validates QBO employee mappings during authentication.
 */
class QboEmployeeLoginGuardService
{
    /**
     * Injects token resolution and employee validation services.
     *
     * @param  QuickBooksTokenResolverService  $tokenResolver  QuickBooks token resolver.
     * @param  QboEmployeeService  $qboEmployee  Employee existence checks.
     */
    public function __construct(
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly QboEmployeeService $qboEmployee,
    ) {}

    /**
     * Logs out and returns a JSON response when the mapped QBO employee was removed.
     *
     * @param  User  $user  Authenticated user.
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse|null
     */
    public function rejectIfEmployeeRemoved(User $user, Request $request): ?JsonResponse
    {
        if ($user->isAdmin() || $user->qbo_employee_ref === null || $user->qbo_employee_ref === '') {
            return null;
        }

        $token = $this->tokenResolver->resolve($user);
        $employee = $this->qboEmployee->findEmployee($token, (string) $user->qbo_employee_ref);

        if ($employee === null) {
            return $this->denyLogin($request, ApiErrorCode::QboEmployeeRemoved, __('api.qbo_employee_removed'));
        }

        if ($employee['email'] === null) {
            return $this->denyLogin($request, ApiErrorCode::QboEmployeeEmailMissing, __('api.qbo_employee_email_missing'));
        }

        try {
            $this->qboEmployee->syncTimesheetUserFromIdentity($user, [
                'first_name' => $employee['first_name'],
                'last_name' => $employee['last_name'],
                'display_name' => $employee['display_name'],
                'email' => $employee['email'],
            ]);
        } catch (ValidationException) {
            return $this->denyLogin($request, ApiErrorCode::QboEmployeeEmailConflict, __('api.qbo_employee_email_conflict'));
        }

        return null;
    }

    /**
     * Logs out and returns a JSON response for a QuickBooks login guard failure.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  ApiErrorCode  $code  API error code.
     * @param  string  $message  User-facing error message.
     * @return JsonResponse
     */
    private function denyLogin(Request $request, ApiErrorCode $code, string $message): JsonResponse
    {
        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => $message,
            'error' => $code->value,
        ], 403);
    }
}
