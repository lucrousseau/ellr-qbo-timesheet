<?php

/**
 * REST endpoints for supervisor time entry approval before QuickBooks sync.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListTimeEntryRequest;
use App\Http\Requests\RejectTimeEntryRequest;
use App\Services\TimeEntryApprovalService;
use App\Support\TimeEntryApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lists pending entries and approves or rejects them for QBO synchronization.
 */
class TimeEntryApprovalController extends Controller
{
    /**
     * Injects the approval workflow service.
     *
     * @param  TimeEntryApprovalService  $approvals  Time entry approval service instance.
     */
    public function __construct(
        private readonly TimeEntryApprovalService $approvals,
    ) {}

    /**
     * Lists pending time entries the actor may review.
     *
     * @param  ListTimeEntryRequest  $request  Validated list query parameters.
     * @return JsonResponse
     */
    public function index(ListTimeEntryRequest $request): JsonResponse
    {
        return response()->json($this->approvals->listPendingForReviewer(
            $request->user(),
            $request->listStartPosition(),
            $request->listMaxResults(),
        ));
    }

    /**
     * Approves a pending entry and synchronizes it to QuickBooks.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  int  $timeEntry  Local time entry identifier.
     * @return JsonResponse
     */
    public function approve(Request $request, int $timeEntry): JsonResponse
    {
        $entry = $this->approvals->approve($request->user(), $timeEntry);

        return response()->json([
            'data' => TimeEntryApiResponse::resource($entry),
        ]);
    }

    /**
     * Rejects a pending entry without synchronizing it to QuickBooks.
     *
     * @param  RejectTimeEntryRequest  $request  Validated rejection payload.
     * @param  int  $timeEntry  Local time entry identifier.
     * @return JsonResponse
     */
    public function reject(RejectTimeEntryRequest $request, int $timeEntry): JsonResponse
    {
        $entry = $this->approvals->reject(
            $request->user(),
            $timeEntry,
            $request->validated('reason'),
        );

        return response()->json([
            'data' => TimeEntryApiResponse::resource($entry),
        ]);
    }
}
