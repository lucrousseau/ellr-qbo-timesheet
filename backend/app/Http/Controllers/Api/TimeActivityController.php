<?php

/**
 * REST endpoints for listing and mutating QuickBooks time activities.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeActivityRequest;
use App\Http\Requests\UpdateTimeActivityRequest;
use App\Services\QuickBooksTokenResolverService;
use App\Services\TimeActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Authorizes requests and delegates time activity CRUD to TimeActivityService.
 */
class TimeActivityController extends Controller
{
    /**
     * Injects token resolution and time activity services.
     *
     * @param  QuickBooksTokenResolverService  $tokenResolver  Resolves the user's QBO token.
     * @param  TimeActivityService  $timeActivities  Time activity service instance.
     */
    public function __construct(
        private readonly QuickBooksTokenResolverService $tokenResolver,
        private readonly TimeActivityService $timeActivities,
    ) {}

    /**
     * Lists time activities for the configured QBO employee.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $this->tokenResolver->resolve($user);

        return response()->json([
            'data' => $this->timeActivities->listForUser($user, $token),
        ]);
    }

    /**
     * Creates a time activity in QuickBooks.
     *
     * @param  StoreTimeActivityRequest  $request  Validated create request.
     * @return JsonResponse
     */
    public function store(StoreTimeActivityRequest $request): JsonResponse
    {
        $user = $request->user();
        $token = $this->tokenResolver->resolve($user);

        $result = $this->timeActivities->createForUser($user, $token, $request->validated());

        return response()->json(['data' => $result], 201);
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
     * Updates an existing time activity.
     *
     * @param  UpdateTimeActivityRequest  $request  Validated update request.
     * @param  string  $id  QuickBooks time activity identifier.
     * @return JsonResponse
     */
    public function update(UpdateTimeActivityRequest $request, string $id): JsonResponse
    {
        $user = $request->user();
        $token = $this->tokenResolver->resolve($user);

        $result = $this->timeActivities->updateForUser($user, $token, $id, $request->validated());

        return response()->json(['data' => $result]);
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
