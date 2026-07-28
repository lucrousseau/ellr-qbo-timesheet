<?php

/**
 * Administrator endpoints for listing and provisioning timesheet users.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Services\UserProvisioningService;
use App\Support\UserApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lists timesheet users and creates accounts linked to QuickBooks employees.
 */
class AdminUserController extends Controller
{
    /**
     * Injects the user provisioning service.
     *
     * @param  UserProvisioningService  $provisioning  Timesheet user provisioning.
     */
    public function __construct(
        private readonly UserProvisioningService $provisioning,
    ) {}

    /**
     * Lists timesheet users with QuickBooks employee mappings.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $users = $this->provisioning->listTimesheetUsers()
            ->map(fn ($user) => UserApiResponse::resource($user))
            ->values();

        return response()->json(['data' => $users]);
    }

    /**
     * Creates a timesheet user for a QuickBooks employee and sends a password-set email.
     *
     * @param  StoreAdminUserRequest  $request  Validated provisioning payload.
     * @return JsonResponse
     */
    public function store(StoreAdminUserRequest $request): JsonResponse
    {
        $user = $this->provisioning->provisionTimesheetUser(
            $request->user(),
            $request->validated(),
        );

        return response()->json(['user' => UserApiResponse::resource($user)], 201);
    }
}
