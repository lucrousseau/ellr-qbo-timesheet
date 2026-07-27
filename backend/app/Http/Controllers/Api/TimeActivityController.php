<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ResolvesQuickBooksToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTimeActivityRequest;
use App\Http\Requests\UpdateTimeActivityRequest;
use App\Services\QuickBooksService;
use App\Services\TimeActivityService;
use Illuminate\Http\JsonResponse;

class TimeActivityController extends Controller
{
    use ResolvesQuickBooksToken;

    public function __construct(
        private readonly QuickBooksService $quickBooks,
        private readonly TimeActivityService $timeActivities,
    ) {}

    protected function quickBooksService(): QuickBooksService
    {
        return $this->quickBooks;
    }

    public function index(): JsonResponse
    {
        $user = auth()->user();
        $token = $this->resolveQuickBooksToken();

        return response()->json([
            'data' => $this->timeActivities->listForUser($user, $token),
        ]);
    }

    public function store(StoreTimeActivityRequest $request): JsonResponse
    {
        $user = auth()->user();
        $token = $this->resolveQuickBooksToken();

        $result = $this->timeActivities->createForUser($user, $token, $request->validated());

        return response()->json(['data' => $result], 201);
    }

    public function show(string $id): JsonResponse
    {
        $user = auth()->user();
        $token = $this->resolveQuickBooksToken();

        return response()->json([
            'data' => $this->timeActivities->findForUser($user, $token, $id),
        ]);
    }

    public function update(UpdateTimeActivityRequest $request, string $id): JsonResponse
    {
        $user = auth()->user();
        $token = $this->resolveQuickBooksToken();

        $result = $this->timeActivities->updateForUser($user, $token, $id, $request->validated());

        return response()->json(['data' => $result]);
    }

    public function destroy(string $id): JsonResponse
    {
        $user = auth()->user();
        $token = $this->resolveQuickBooksToken();

        $this->timeActivities->deleteForUser($user, $token, $id);

        return response()->json(null, 204);
    }
}
