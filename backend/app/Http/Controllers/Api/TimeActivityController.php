<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\QuickBooksException;
use App\Http\Controllers\Controller;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\QuickBooksService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use QuickBooksOnline\API\Facades\TimeActivity;

class TimeActivityController extends Controller
{
    private const MAX_EMPLOYEE_REF_LENGTH = 255;

    private const MAX_NAME_LENGTH = 255;

    public function __construct(
        private readonly QuickBooksService $quickBooks,
    ) {}

    public function index(): JsonResponse
    {
        $employeeRef = $this->resolveEmployeeRef();
        $token = $this->resolveToken();
        $dataService = $this->quickBooks->dataService($token);

        $activities = $dataService->Query(
            "SELECT * FROM TimeActivity WHERE EmployeeRef = '{$this->escapeQueryValue($employeeRef)}' MAXRESULTS 100",
        );

        if ($error = $dataService->getLastError()) {
            return $this->quickBooksApiError($error);
        }

        return response()->json(['data' => is_array($activities) ? $activities : []]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_ref' => ['nullable', 'string', 'max:'.self::MAX_EMPLOYEE_REF_LENGTH],
            'customer_name' => ['nullable', 'string', 'max:'.self::MAX_NAME_LENGTH],
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'description' => ['nullable', 'string', 'max:4000'],
        ]);

        $employeeRef = $this->resolveEmployeeRef();
        $user = auth()->user();
        $token = $this->resolveToken();
        $dataService = $this->quickBooks->dataService($token);

        $employeePayload = ['value' => $employeeRef];
        if (! empty($user->qbo_employee_name)) {
            $employeePayload['name'] = $user->qbo_employee_name;
        }

        $timeActivityPayload = [
            'NameOf' => 'Employee',
            'EmployeeRef' => $employeePayload,
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

        $this->assertActivityBelongsToUser($activity);

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

        $this->assertActivityBelongsToUser($existing);

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

        $this->assertActivityBelongsToUser($existing);

        $dataService->Delete($existing);

        if ($error = $dataService->getLastError()) {
            return $this->quickBooksApiError($error);
        }

        return response()->json(null, 204);
    }

    private function resolveEmployeeRef(): string
    {
        /** @var User|null $user */
        $user = auth()->user();
        $employeeRef = $user?->qbo_employee_ref;

        if ($employeeRef === null || $employeeRef === '') {
            abort(response()->json([
                'message' => 'QBO employee is not configured for this user.',
                'error' => 'qbo_employee_not_configured',
            ], 403));
        }

        return $employeeRef;
    }

    private function assertActivityBelongsToUser(object $activity): void
    {
        $employeeRef = $this->resolveEmployeeRef();
        $activityEmployeeRef = $this->extractEmployeeRef($activity);

        if ($activityEmployeeRef !== $employeeRef) {
            abort(response()->json(['message' => 'Time activity not found'], 404));
        }
    }

    private function extractEmployeeRef(object $activity): ?string
    {
        $employeeRef = $activity->EmployeeRef ?? null;

        if ($employeeRef === null) {
            return null;
        }

        if (is_object($employeeRef) && isset($employeeRef->value)) {
            return (string) $employeeRef->value;
        }

        return (string) $employeeRef;
    }

    private function escapeQueryValue(string $value): string
    {
        return str_replace("'", "\\'", $value);
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
