<?php

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Models\QuickBooksToken;
use App\Models\User;
use QuickBooksOnline\API\Facades\TimeActivity;

class TimeActivityService
{
    public function __construct(
        private readonly QuickBooksService $quickBooks,
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function listForUser(User $user, QuickBooksToken $token): array
    {
        $employeeRef = $this->resolveEmployeeRef($user);
        $dataService = $this->quickBooks->dataService($token);

        $activities = $dataService->Query(
            "SELECT * FROM TimeActivity WHERE EmployeeRef = '{$this->escapeQueryValue($employeeRef)}' MAXRESULTS 100",
        );

        if ($error = $dataService->getLastError()) {
            abort($this->quickBooks->apiErrorJsonResponse($error));
        }

        return is_array($activities) ? $activities : [];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createForUser(User $user, QuickBooksToken $token, array $validated): object
    {
        $employeeRef = $this->resolveEmployeeRef($user);
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
            abort($this->quickBooks->apiErrorJsonResponse($error));
        }

        return $result;
    }

    public function findForUser(User $user, QuickBooksToken $token, string $id): object
    {
        $dataService = $this->quickBooks->dataService($token);

        return $this->findActivityForUser($user, $dataService, $id);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateForUser(User $user, QuickBooksToken $token, string $id, array $validated): object
    {
        $dataService = $this->quickBooks->dataService($token);
        $existing = $this->findActivityForUser($user, $dataService, $id);

        $startTime = $validated['start_time'] ?? $existing->StartTime ?? null;
        $endTime = $validated['end_time'] ?? $existing->EndTime ?? null;

        if ($endTime !== null && $startTime !== null && strtotime((string) $endTime) <= strtotime((string) $startTime)) {
            abort(response()->json([
                'message' => 'The end time field must be a date after start time.',
                'errors' => ['end_time' => ['The end time field must be a date after start time.']],
            ], 422));
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
            abort($this->quickBooks->apiErrorJsonResponse($error));
        }

        return $result;
    }

    public function deleteForUser(User $user, QuickBooksToken $token, string $id): void
    {
        $dataService = $this->quickBooks->dataService($token);
        $existing = $this->findActivityForUser($user, $dataService, $id);

        $dataService->Delete($existing);

        if ($error = $dataService->getLastError()) {
            abort($this->quickBooks->apiErrorJsonResponse($error));
        }
    }

    private function findActivityForUser(User $user, object $dataService, string $id): object
    {
        $activity = $dataService->FindById('TimeActivity', $id);

        if ($error = $dataService->getLastError()) {
            abort($this->quickBooks->apiErrorJsonResponse($error));
        }

        if (! $activity) {
            abort(response()->json(['message' => 'Time activity not found'], 404));
        }

        $this->assertActivityBelongsToUser($user, $activity);

        return $activity;
    }

    private function resolveEmployeeRef(User $user): string
    {
        $employeeRef = $user->qbo_employee_ref;

        if ($employeeRef === null || $employeeRef === '') {
            abort(response()->json([
                'message' => 'QBO employee is not configured for this user.',
                'error' => ApiErrorCode::QboEmployeeNotConfigured->value,
            ], 403));
        }

        return $employeeRef;
    }

    private function assertActivityBelongsToUser(User $user, object $activity): void
    {
        $employeeRef = $this->resolveEmployeeRef($user);
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
}
