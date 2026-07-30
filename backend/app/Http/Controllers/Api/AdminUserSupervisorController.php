<?php

/**
 * Administrator endpoint for assigning employee supervisors.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAdminUserSupervisorRequest;
use App\Models\User;
use App\Services\UserSupervisorService;
use App\Support\UserApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Updates supervisor assignments for provisioned timesheet users.
 */
class AdminUserSupervisorController extends Controller
{
    /**
     * Injects the supervisor assignment service.
     *
     * @param  UserSupervisorService  $supervisors  Supervisor assignment service.
     */
    public function __construct(
        private readonly UserSupervisorService $supervisors,
    ) {}

    /**
     * Assigns or clears the supervisor for a timesheet user.
     *
     * @param  UpdateAdminUserSupervisorRequest  $request  Validated supervisor payload.
     * @param  User  $user  Target timesheet user.
     * @return JsonResponse
     */
    public function update(UpdateAdminUserSupervisorRequest $request, User $user): JsonResponse
    {
        $updated = $this->supervisors->updateSupervisor(
            $request->user(),
            $user,
            $request->supervisorId(),
        );

        return response()->json([
            'data' => UserApiResponse::resource($updated),
        ]);
    }
}
