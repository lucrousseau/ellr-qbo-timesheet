<?php

/**
 * Administrator endpoints for timesheet user customer assignments.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SyncAdminUserCustomersRequest;
use App\Models\User;
use App\Services\QuickBooksTokenResolverService;
use App\Services\UserQboCustomerAssignmentService;
use Illuminate\Http\JsonResponse;

/**
 * Reads and updates QuickBooks customer access for provisioned timesheet users.
 */
class AdminUserCustomerController extends Controller
{
    /**
     * Injects customer assignment and QuickBooks token services.
     *
     * @param  UserQboCustomerAssignmentService  $assignments  Customer assignment service.
     * @param  QuickBooksTokenResolverService  $tokenResolver  QuickBooks token resolver.
     */
    public function __construct(
        private readonly UserQboCustomerAssignmentService $assignments,
        private readonly QuickBooksTokenResolverService $tokenResolver,
    ) {}

    /**
     * Lists customers assigned to the given timesheet user.
     *
     * @param  User  $user  Target timesheet user.
     * @return JsonResponse
     */
    public function show(User $user): JsonResponse
    {
        $this->assignments->ensureTimesheetUser($user);

        return response()->json($this->assignments->describeAccess($user));
    }

    /**
     * Replaces customer assignments for the given timesheet user.
     *
     * @param  SyncAdminUserCustomersRequest  $request  Validated customer identifiers.
     * @param  User  $user  Target timesheet user.
     * @return JsonResponse
     */
    public function update(SyncAdminUserCustomersRequest $request, User $user): JsonResponse
    {
        $token = $this->tokenResolver->resolve($request->user());
        $access = $this->assignments->sync(
            $user,
            $token,
            $request->boolean('all_customers_access'),
            $request->validated('customer_refs', []),
        );

        return response()->json($access);
    }
}
