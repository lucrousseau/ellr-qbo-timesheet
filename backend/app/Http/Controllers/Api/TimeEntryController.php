<?php

/**
 * REST endpoints for local time entries awaiting supervisor approval.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListTimeEntryRequest;
use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksTokenResolverService;
use App\Services\TimeEntryListService;
use App\Services\TimeEntryPresentationService;
use App\Services\TimeEntryService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authorizes requests and delegates local time entry CRUD to TimeEntryService.
 */
class TimeEntryController extends Controller
{
    /**
     * @param  QuickBooksTokenResolverService  $tokenResolver  Organization QBO token resolver.
     * @param  TimeEntryListService  $timeEntryList  Merged local and snapshot list service.
     * @param  TimeEntryService  $timeEntries  Local time entry writer.
     * @param  TimeEntryPresentationService  $presentation  Read-time QBO label resolver.
     */
    public function __construct(
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly TimeEntryListService $timeEntryList,
        private readonly TimeEntryService $timeEntries,
        private readonly TimeEntryPresentationService $presentation,
    ) {}

    /**
     * Lists time entries for the authenticated employee.
     *
     * @param  ListTimeEntryRequest  $request  Validated list query parameters.
     * @return JsonResponse
     */
    public function index(ListTimeEntryRequest $request): JsonResponse
    {
        $user = $request->user();
        $token = $this->resolveOptionalToken($user);

        return response()->json($this->timeEntryList->listForUser(
            $user,
            $token,
            $request->listStartPosition(),
            $request->listMaxResults(),
            $request->listRefresh(),
        ));
    }

    /**
     * Creates a draft time entry for the employee.
     *
     * @param  StoreTimeEntryRequest  $request  Validated create request.
     * @return JsonResponse
     */
    public function store(StoreTimeEntryRequest $request): JsonResponse
    {
        $entry = $this->timeEntries->createForUser($request->user(), $request->validated());

        return response()->json([
            'data' => $this->presentation->resource($entry->load('user'), $request->user()),
        ], 201);
    }

    /**
     * Updates a draft or rejected time entry owned by the authenticated employee.
     *
     * @param  UpdateTimeEntryRequest  $request  Validated update request.
     * @param  int  $timeEntry  Local time entry identifier.
     * @return JsonResponse
     */
    public function update(UpdateTimeEntryRequest $request, int $timeEntry): JsonResponse
    {
        $entry = $this->timeEntries->updateForUser(
            $request->user(),
            $timeEntry,
            $request->validated(),
        );

        return response()->json([
            'data' => $this->presentation->resource($entry->load('user'), $request->user()),
        ]);
    }

    /**
     * Deletes a draft or rejected time entry owned by the authenticated employee.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  int  $timeEntry  Local time entry identifier.
     * @return JsonResponse
     */
    public function destroy(Request $request, int $timeEntry): JsonResponse
    {
        $this->timeEntries->deleteForUser($request->user(), $timeEntry);

        return response()->json(null, 204);
    }

    /**
     * Resolves a QuickBooks token when connected, otherwise returns null for local-only lists.
     *
     * @param  User  $user  Authenticated application user.
     * @return QuickBooksToken|null
     */
    private function resolveOptionalToken(User $user): ?QuickBooksToken
    {
        try { // @pest-mutate-ignore optional QBO token resolution
            return $this->tokenResolver->resolve($user);
        } catch (HttpResponseException) { // @pest-mutate-ignore optional QBO token resolution
            return null;
        }
    }
}
