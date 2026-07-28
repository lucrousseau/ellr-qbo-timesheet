<?php

/**
 * Administrator endpoints for mapping QuickBooks employees to user accounts.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateQboEmployeeRequest;
use App\Models\User;
use App\Services\QboEmployeeService;
use App\Support\UserApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Allows administrators to link a QBO employee to any application user.
 */
class AdminQboEmployeeController extends Controller
{
    /**
     * Injects the QBO employee mapping service.
     *
     * @param  QboEmployeeService  $qboEmployee  Employee mapping service.
     */
    public function __construct(
        private readonly QboEmployeeService $qboEmployee,
    ) {}

    /**
     * Associates a QuickBooks employee with the given user account.
     *
     * @param  UpdateQboEmployeeRequest  $request  Validated employee mapping payload.
     * @param  User  $user  Target user account.
     * @return JsonResponse
     */
    public function update(UpdateQboEmployeeRequest $request, User $user): JsonResponse
    {
        $updated = $this->qboEmployee->updateMapping(
            $request->user(),
            $user,
            $request->validated(),
        );

        return response()->json(['user' => UserApiResponse::resource($updated)]);
    }
}
