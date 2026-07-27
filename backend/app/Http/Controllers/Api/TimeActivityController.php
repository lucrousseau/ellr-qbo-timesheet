<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\QuickBooksException;
use App\Http\Controllers\Controller;
use App\Models\QuickBooksToken;
use App\Services\QuickBooksService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QuickBooksOnline\API\Facades\TimeActivity;

class TimeActivityController extends Controller
{
    public function __construct(
        private readonly QuickBooksService $quickBooks,
    ) {}

    public function index(): JsonResponse
    {
        $token = $this->resolveToken();
        $dataService = $this->quickBooks->dataService($token);

        $activities = $dataService->Query('SELECT * FROM TimeActivity MAXRESULTS 100');

        if ($error = $dataService->getLastError()) {
            return $this->quickBooksApiError($error);
        }

        return response()->json(['data' => is_array($activities) ? $activities : []]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_ref' => ['required', 'string'],
            'employee_name' => ['nullable', 'string'],
            'customer_ref' => ['nullable', 'string'],
            'customer_name' => ['nullable', 'string'],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'description' => ['nullable', 'string', 'max:4000'],
        ]);

        $token = $this->resolveToken();
        $dataService = $this->quickBooks->dataService($token);

        $employeeRef = ['value' => $validated['employee_ref']];
        if (! empty($validated['employee_name'])) {
            $employeeRef['name'] = $validated['employee_name'];
        }

        $timeActivityPayload = [
            'NameOf' => 'Employee',
            'EmployeeRef' => $employeeRef,
            'StartTime' => $validated['start_time'],
            'EndTime' => $validated['end_time'],
        ];

        if (array_key_exists('description', $validated) && $validated['description'] !== null) {
            $timeActivityPayload['Description'] = $validated['description'];
        }

        if (! empty($validated['customer_ref'])) {
            $customerRef = ['value' => $validated['customer_ref']];
            if (! empty($validated['customer_name'])) {
                $customerRef['name'] = $validated['customer_name'];
            }
            $timeActivityPayload['CustomerRef'] = $customerRef;
        }

        $timeActivity = TimeActivity::create($timeActivityPayload);

        $result = $dataService->Add($timeActivity);

        if ($error = $dataService->getLastError()) {
            return $this->quickBooksApiError($error);
        }

        return response()->json(['data' => $result], 201);
    }

    public function show(string $id): JsonResponse
    {
        $token = $this->resolveToken();
        $dataService = $this->quickBooks->dataService($token);

        $activity = $dataService->FindById('TimeActivity', $id);

        if ($error = $dataService->getLastError()) {
            return $this->quickBooksApiError($error);
        }

        if (! $activity) {
            return response()->json(['message' => 'Time activity not found'], 404);
        }

        return response()->json(['data' => $activity]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'start_time' => ['sometimes', 'date'],
            'end_time' => ['sometimes', 'date'],
            'description' => ['nullable', 'string', 'max:4000'],
        ]);

        $token = $this->resolveToken();
        $dataService = $this->quickBooks->dataService($token);

        $existing = $dataService->FindById('TimeActivity', $id);

        if ($error = $dataService->getLastError()) {
            return $this->quickBooksApiError($error);
        }

        if (! $existing) {
            return response()->json(['message' => 'Time activity not found'], 404);
        }

        $startTime = $validated['start_time'] ?? $existing->StartTime ?? null;
        $endTime = $validated['end_time'] ?? $existing->EndTime ?? null;

        if ($endTime !== null && $startTime !== null && strtotime((string) $endTime) <= strtotime((string) $startTime)) {
            return response()->json([
                'message' => 'The end time field must be a date after start time.',
                'errors' => ['end_time' => ['The end time field must be a date after start time.']],
            ], 422);
        }

        $payload = [
            'Id' => $id,
            'SyncToken' => $existing->SyncToken,
        ];

        if (array_key_exists('start_time', $validated)) {
            $payload['StartTime'] = $validated['start_time'];
        }

        if (array_key_exists('end_time', $validated)) {
            $payload['EndTime'] = $validated['end_time'];
        }

        if (array_key_exists('description', $validated)) {
            $payload['Description'] = $validated['description'];
        }

        $timeActivity = TimeActivity::update($existing, $payload);
        $result = $dataService->Update($timeActivity);

        if ($error = $dataService->getLastError()) {
            return $this->quickBooksApiError($error);
        }

        return response()->json(['data' => $result]);
    }

    public function destroy(string $id): JsonResponse
    {
        $token = $this->resolveToken();
        $dataService = $this->quickBooks->dataService($token);

        $existing = $dataService->FindById('TimeActivity', $id);

        if ($error = $dataService->getLastError()) {
            return $this->quickBooksApiError($error);
        }

        if (! $existing) {
            return response()->json(['message' => 'Time activity not found'], 404);
        }

        $dataService->Delete($existing);

        if ($error = $dataService->getLastError()) {
            return $this->quickBooksApiError($error);
        }

        return response()->json(null, 204);
    }

    private function resolveToken(): QuickBooksToken
    {
        $user = auth()->user();

        if ($user === null) {
            abort(401);
        }

        $token = $user->quickBooksToken;

        if (! $token) {
            $this->denyQuickBooks(
                'quickbooks_not_connected',
                'QuickBooks is not connected.',
            );
        }

        try {
            if ($token->isAccessTokenExpired()) {
                $token = $this->quickBooks->refreshToken($token);
            }
        } catch (LockTimeoutException) {
            abort(response()->json([
                'message' => 'QuickBooks is busy. Please retry.',
                'error' => 'quickbooks_busy',
            ], 503));
        } catch (QuickBooksException) {
            $this->denyQuickBooks(
                'quickbooks_expired',
                'QuickBooks connection expired. Please reconnect from the admin app.',
            );
        }

        return $token;
    }

    private function quickBooksApiError(object $error): JsonResponse
    {
        $payload = ['message' => 'QuickBooks API error'];

        if (config('quickbooks.expose_api_errors')) {
            $payload['error'] = $error->getResponseBody();
        }

        return response()->json($payload, 422);
    }

    private function denyQuickBooks(string $code, string $message): never
    {
        abort(response()->json([
            'message' => $message,
            'error' => $code,
        ], 403));
    }
}
