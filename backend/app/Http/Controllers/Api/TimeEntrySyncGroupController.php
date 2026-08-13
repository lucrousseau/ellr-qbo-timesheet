<?php

/**
 * REST endpoint for QuickBooks sync group drill-down used by accountants.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TimeEntrySyncGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exposes sync group membership for Ellr deep links embedded in QuickBooks notes.
 */
class TimeEntrySyncGroupController extends Controller
{
    /**
     * Injects the sync group read service.
     *
     * @param  TimeEntrySyncGroupService  $syncGroups  Sync group detail service.
     */
    public function __construct(
        private readonly TimeEntrySyncGroupService $syncGroups,
    ) {}

    /**
     * Shows one sync group and its local member entries.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $publicId  Sync group public UUID.
     * @return JsonResponse
     */
    public function show(Request $request, string $publicId): JsonResponse
    {
        return response()->json(
            $this->syncGroups->showForActor($request->user(), $publicId),
        );
    }
}
