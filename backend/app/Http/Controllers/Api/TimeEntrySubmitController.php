<?php

/**
 * REST endpoints for submitting draft time entries for supervisor approval.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitTimeEntriesRequest;
use App\Services\TimeEntryPresentationService;
use App\Services\TimeEntrySubmitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Submits owned draft or rejected time entries into the pending review queue.
 */
class TimeEntrySubmitController extends Controller
{
    /**
     * @param  TimeEntrySubmitService  $submissions  Submit workflow service.
     * @param  TimeEntryPresentationService  $presentation  Read-time QBO label resolver.
     */
    public function __construct(
        private readonly TimeEntrySubmitService $submissions,
        private readonly TimeEntryPresentationService $presentation,
    ) {}

    /**
     * Submits one owned draft or rejected time entry for approval.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  int  $timeEntry  Local time entry identifier.
     * @return JsonResponse
     */
    public function submitOne(Request $request, int $timeEntry): JsonResponse
    {
        $entry = $this->submissions->submitForUser($request->user(), $timeEntry);

        return response()->json([
            'data' => $this->presentation->resource($entry->load('user'), $request->user()),
        ]);
    }

    /**
     * Submits owned draft or rejected time entries for approval.
     *
     * @param  SubmitTimeEntriesRequest  $request  Optional entry id filter.
     * @return JsonResponse
     */
    public function submitMany(SubmitTimeEntriesRequest $request): JsonResponse
    {
        $entries = $this->submissions->submitManyForUser(
            $request->user(),
            $request->validated('ids') ?? null,
        );
        $entries->each(fn ($entry) => $entry->loadMissing('user'));

        return response()->json([
            'data' => $this->presentation->collection($entries, $request->user()),
        ]);
    }
}
