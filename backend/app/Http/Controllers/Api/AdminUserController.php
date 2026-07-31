<?php

/**
 * Administrator endpoints for listing and provisioning timesheet users.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminUserRequest;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksTokenResolverService;
use App\Services\UserProvisioningService;
use App\Services\UserQboCustomerAssignmentService;
use App\Support\UserApiResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
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
     * @param  QuickBooksTokenResolverService  $tokenResolver  QuickBooks token resolver.
     * @param  UserQboCustomerAssignmentService  $customerAssignments  Customer access resolver.
     */
    public function __construct(
        private readonly UserProvisioningService $provisioning,
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly UserQboCustomerAssignmentService $customerAssignments,
    ) {}

    /**
     * Lists timesheet users with QuickBooks employee mappings.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $token = $this->tryResolveToken($request->user());
        $users = $this->provisioning->listTimesheetUsers($request->user())
            ->load(['qboCustomers', 'organization', 'userLevel'])
            ->map(function ($user) use ($token) {
                $resource = UserApiResponse::resource($user);
                $access = $this->customerAssignments->describeAccessForApi($user, $token);
                $resource->setAttribute('all_customers_access', $access['all_customers_access']);
                $resource->setAttribute('assigned_customers', $access['data']);
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

    /**
     * Resolves a QuickBooks token when connected, otherwise returns null for local-only rows.
     *
     * @param  User  $user  Authenticated application user.
     * @return QuickBooksToken|null
     */
    private function tryResolveToken(User $user): ?QuickBooksToken
    {
        try {
            return $this->tokenResolver->resolve($user);
        } catch (HttpResponseException) {
            return null;
        }
    }
}
