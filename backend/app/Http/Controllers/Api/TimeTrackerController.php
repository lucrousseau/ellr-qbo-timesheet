<?php

/**
 * Active timer session API for persistent timesheet tracking.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTimeTrackerRequest;
use App\Services\QuickBooksTokenResolverService;
use App\Services\TimeTrackerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Reads, updates, logs, and discards server-side timer sessions.
 */
class TimeTrackerController extends Controller
{
    /**
     * Injects timer persistence and QuickBooks token resolution.
     *
     * @param  TimeTrackerService  $timeTracker  Active timer session service.
     * @param  QuickBooksTokenResolverService  $tokenResolver  QuickBooks token resolver.
     */
    public function __construct(
        private readonly TimeTrackerService $timeTracker,
        private readonly QuickBooksTokenResolverService $tokenResolver,
    ) {}

    /**
     * Returns the active timer session for the authenticated user.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $session = $this->timeTracker->findForUser($user);

        if ($session !== null && $session->hasPickerSelections()) {
            $token = $this->tokenResolver->resolve($user);
            $session = $this->timeTracker->sanitizeForUser($user, $token, $session);
        }

        return response()->json(['data' => $this->timeTracker->toApi($session)]);
    }

    /**
     * Creates or updates the active timer session for the authenticated user.
     *
     * @param  UpdateTimeTrackerRequest  $request  Validated timer payload.
     * @return JsonResponse
     */
    public function update(UpdateTimeTrackerRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $token = $this->needsQuickBooksValidation($validated)
            ? $this->tokenResolver->resolve($user)
            : null;
        $session = $this->timeTracker->upsertForUser($user, $token, $validated);

        return response()->json(['data' => $this->timeTracker->toApi($session)]);
    }

    /**
     * Logs elapsed time to QuickBooks and clears the active session.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function log(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $this->tokenResolver->resolve($user);
        $activity = $this->timeTracker->logForUser($user, $token);

        return response()->json(['data' => $activity]);
    }

    /**
     * Discards the active timer session without logging time.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return Response
     */
    public function destroy(Request $request): Response
    {
        $this->timeTracker->discardForUser($request->user());

        return response()->noContent();
    }

    /**
     * Returns whether picker references require QuickBooks validation.
     *
     * @param  array<string, mixed>  $validated  Validated timer payload.
     * @return bool
     */
    private function needsQuickBooksValidation(array $validated): bool
    {
        foreach (['customer_ref', 'project_ref', 'service_ref'] as $field) {
            $value = $validated[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
