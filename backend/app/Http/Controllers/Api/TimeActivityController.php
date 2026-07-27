<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ResolvesQuickBooksToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeActivityRequest;
use App\Http\Requests\UpdateTimeActivityRequest;
use App\Services\QuickBooksService;
use App\Services\TimeActivityService;
use Illuminate\Http\JsonResponse;

/**
 * CRUD for QuickBooks time activities for the employee linked to the user.
 */
class TimeActivityController extends Controller
{
    use ResolvesQuickBooksToken;

    /**
     * Injects QuickBooks and TimeActivity services.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  TimeActivityService  $timeActivities  Time activity service instance.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly TimeActivityService $timeActivities,
    ) {}

    /**
     * Injected QuickBooks service for the token resolution trait.
     *
     * @return QuickBooksService
     */
    protected function quickBooksService(): QuickBooksService
    {
        return $this->quickBooks;
    }

    /**
     * Lists time activities for the configured QBO employee.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $token = $this->resolveQuickBooksToken();

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
        $user = auth()->user();
        $token = $this->resolveQuickBooksToken();

        $result = $this->timeActivities->createForUser($user, $token, $request->validated());

        return response()->json(['data' => $result], 201);
    }

    /**
     * Displays a time activity by QBO identifier.
     *
     * @param  string  $id  QuickBooks time activity identifier.
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $user = auth()->user();
        $token = $this->resolveQuickBooksToken();

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
        $user = auth()->user();
        $token = $this->resolveQuickBooksToken();

        $result = $this->timeActivities->updateForUser($user, $token, $id, $request->validated());

        return response()->json(['data' => $result]);
    }

    /**
     * Deletes a time activity in QuickBooks.
     *
     * @param  string  $id  QuickBooks time activity identifier.
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $user = auth()->user();
        $token = $this->resolveQuickBooksToken();

        $this->timeActivities->deleteForUser($user, $token, $id);

        return response()->json(null, 204);
    }
}
