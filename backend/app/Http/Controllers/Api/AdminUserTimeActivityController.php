<?php

/**
 * Administrator endpoints for viewing a timesheet user's QuickBooks time activities.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListTimeActivityRequest;
use App\Models\User;
use App\Services\QuickBooksTokenResolverService;
use App\Services\TimeActivityListService;
use App\Services\UserQboCustomerAssignmentService;
use Illuminate\Http\JsonResponse;

/**
 * Lists QuickBooks-synced time activities for provisioned timesheet users.
 */
class AdminUserTimeActivityController extends Controller
{
    /**
     * Injects assignment checks and list services.
     *
     * @param  UserQboCustomerAssignmentService  $assignments  Timesheet user validation.
     * @param  QuickBooksTokenResolverService  $tokenResolver  QuickBooks token resolver.
     * @param  TimeActivityListService  $timeActivityList  Paginated list queries.
     */
    public function __construct(
        private readonly UserQboCustomerAssignmentService $assignments,
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly TimeActivityListService $timeActivityList,
    ) {}

    /**
     * Lists time activities for a provisioned timesheet user.
     *
     * @param  ListTimeActivityRequest  $request  Validated list query parameters.
     * @param  User  $user  Target timesheet user.
     * @return JsonResponse
     */
    public function index(ListTimeActivityRequest $request, User $user): JsonResponse
    {
        $this->assignments->ensureTimesheetUser($request->user(), $user);
        $token = $this->tokenResolver->resolve($request->user());

        return response()->json($this->timeActivityList->listForUser(
            $user,
            $token,
            $request->listStartPosition(),
            $request->listMaxResults(),
            $request->listRefresh(),
        ));
    }
}
