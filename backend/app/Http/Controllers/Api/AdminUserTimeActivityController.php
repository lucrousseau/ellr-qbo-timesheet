<?php

/**
 * Administrator endpoints for viewing and editing a timesheet user's time activities.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListTimeActivityRequest;
use App\Http\Requests\UpdateTimeActivityRequest;
use App\Models\User;
use App\Services\QuickBooksTokenResolverService;
use App\Services\TimeActivityListService;
use App\Services\TimeActivityService;
use App\Services\UserQboCustomerAssignmentService;
use Illuminate\Http\JsonResponse;

/**
 * Lists and updates QuickBooks time activities for provisioned timesheet users.
 */
class AdminUserTimeActivityController extends Controller
{
    /**
     * Injects assignment checks and time activity services.
     *
     * @param  UserQboCustomerAssignmentService  $assignments  Timesheet user validation.
     * @param  QuickBooksTokenResolverService  $tokenResolver  QuickBooks token resolver.
     * @param  TimeActivityListService  $timeActivityList  Paginated list queries.
     * @param  TimeActivityService  $timeActivities  Time activity CRUD service.
     */
    public function __construct(
        private readonly UserQboCustomerAssignmentService $assignments,
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly TimeActivityListService $timeActivityList,
        private readonly TimeActivityService $timeActivities,
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

    /**
     * Updates a time activity for a provisioned timesheet user.
     *
     * @param  UpdateTimeActivityRequest  $request  Validated update request.
     * @param  User  $user  Target timesheet user.
     * @param  string  $id  QuickBooks time activity identifier.
     * @return JsonResponse
     */
    public function update(UpdateTimeActivityRequest $request, User $user, string $id): JsonResponse
    {
        $this->assignments->ensureTimesheetUser($request->user(), $user);
        $token = $this->tokenResolver->resolve($request->user());

        $result = $this->timeActivities->updateForUser($user, $token, $id, $request->validated());

        return response()->json(['data' => $result]);
    }
}
