<?php

/**
 * REST endpoints for listing and mutating QuickBooks time activities.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListTimeActivityRequest;
use App\Http\Requests\StoreTimeActivityRequest;
use App\Services\QuickBooksTokenResolverService;
use App\Services\TimeActivityListService;
use App\Services\TimeActivityService;
use App\Services\TimeEntryPresentationService;
use App\Services\TimeEntryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lists QuickBooks time activities and creates local draft entries. */
class TimeActivityController extends Controller
{
    /**
     * @param  QuickBooksTokenResolverService  $tokenResolver  Organization QBO token resolver.
     * @param  TimeActivityListService  $timeActivityList  Snapshot list service.
     * @param  TimeActivityService  $timeActivities  QBO time activity writer.
     * @param  TimeEntryService  $timeEntries  Local time entry writer.
     * @param  TimeEntryPresentationService  $presentation  Read-time QBO label resolver.
     */
    public function __construct(
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly TimeActivityListService $timeActivityList,
        private readonly TimeActivityService $timeActivities,
        private readonly TimeEntryService $timeEntries,
        private readonly TimeEntryPresentationService $presentation,
    ) {}

    /**
     * Lists time activities for the configured QBO employee.
     *
     * @param  ListTimeActivityRequest  $request  Validated list query parameters.
     * @return JsonResponse
     */
    public function index(ListTimeActivityRequest $request): JsonResponse
    {
        $user = $request->user();
        $token = $this->tokenResolver->resolve($user);

        return response()->json($this->timeActivityList->listForUser(
            $user,
            $token,
            $request->listStartPosition(),
            $request->listMaxResults(),
            $request->listRefresh(),
        ));
    }

    /**
     * Creates a draft local time entry for later submission.
     *
     * @param  StoreTimeActivityRequest  $request  Validated create request.
     * @return JsonResponse
     */
    public function store(StoreTimeActivityRequest $request): JsonResponse
    {
        $entry = $this->timeEntries->createForUser($request->user(), $request->validated());

        return response()->json([
            'data' => $this->presentation->resource($entry->load('user'), $request->user()),
        ], 201);
    }

    /**
     * Displays a time activity by QBO identifier.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $id  QuickBooks time activity identifier.
     * @return JsonResponse
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $token = $this->tokenResolver->resolve($user);

        return response()->json([
            'data' => $this->timeActivities->findForUser($user, $token, $id),
        ]);
    }

    /**
     * Deletes a time activity in QuickBooks.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $id  QuickBooks time activity identifier.
     * @return JsonResponse
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $token = $this->tokenResolver->resolve($user);

        $this->timeActivities->deleteForUser($user, $token, $id);

        return response()->json(null, 204);
    }
}
