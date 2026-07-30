<?php

/**
 * Administrator endpoints for listing and provisioning timesheet users.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Models\User;
use App\Services\UserProvisioningService;
use App\Support\UserApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
        $users = $this->provisioning->listTimesheetUsers($request->user())
            ->load(['qboCustomers', 'organization', 'userLevel'])
            ->map(function ($user) {
                $resource = UserApiResponse::resource($user);
                $resource->setAttribute('all_customers_access', $user->qbo_all_customers_access);
                $resource->setAttribute(
                    'assigned_customers',
                    $user->qbo_all_customers_access
                        ? []
                        : $user->qboCustomers
                            ->sortBy('qbo_customer_name')
                            ->map(fn ($assignment) => [
                                'id' => $assignment->qbo_customer_ref,
                                'display_name' => $assignment->qbo_customer_name,
                            ])
                            ->values()
                            ->all(),
                );
                $resource->unsetRelation('qboCustomers');

                return $resource;
            })
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

    /**
     * Removes a provisioned timesheet user account without deleting QuickBooks time entries.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  User  $user  Timesheet user to revoke.
     * @return JsonResponse
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->provisioning->revokeTimesheetUser($request->user(), $user);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
