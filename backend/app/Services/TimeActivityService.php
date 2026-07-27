<?php

namespace App\Services;

use App\Enums\ApiErrorCode;
use App\Models\QuickBooksToken;
use App\Models\User;
use QuickBooksOnline\API\Facades\TimeActivity;

/**
 * Business operations on QuickBooks time activities for a user's employee.
 */
class TimeActivityService
{
    /**
     * Injects the QuickBooks service.
     *
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     */
    public function __construct(
        private readonly QuickBooksService $quickBooks,
    ) {}

    /**
     * Lists time activities for the QBO employee linked to the user.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
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
     * Creates a time activity in QuickBooks for the user's employee.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  array<string, mixed>  $validated  Validated request payload.
     * @return object
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

    /**
     * Returns a time activity if it belongs to the user's employee.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  string  $id  QuickBooks time activity identifier.
     * @return object
     */
    public function findForUser(User $user, QuickBooksToken $token, string $id): object
    {
        $dataService = $this->quickBooks->dataService($token);

        return $this->findActivityForUser($user, $dataService, $id);
    }

    /**
     * Updates an existing time activity in QuickBooks.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  string  $id  QuickBooks time activity identifier.
     * @param  array<string, mixed>  $validated  Validated request payload.
     * @return object
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

    /**
     * Deletes a time activity in QuickBooks.
     *
     * @param  User  $user  Authenticated application user.
     * @param  QuickBooksToken  $token  Valid QuickBooks OAuth token.
     * @param  string  $id  QuickBooks time activity identifier.
     * @return void
     */
    public function deleteForUser(User $user, QuickBooksToken $token, string $id): void
    {
        $dataService = $this->quickBooks->dataService($token);
        $existing = $this->findActivityForUser($user, $dataService, $id);

        $dataService->Delete($existing);

        if ($error = $dataService->getLastError()) {
            abort($this->quickBooks->apiErrorJsonResponse($error));
        }
    }

    /**
     * Loads an activity and verifies it belongs to the user's employee.
     *
     * @param  User  $user  Authenticated application user.
     * @param  object  $dataService  Configured QuickBooks DataService instance.
     * @param  string  $id  QuickBooks time activity identifier.
     * @return object
     */
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

    /**
     * Returns the QBO employee reference or aborts with 403.
     *
     * @param  User  $user  Authenticated application user.
     * @return string
     */
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

    /**
     * Verifies the activity targets the same QBO employee as the user.
     *
     * @param  User  $user  Authenticated application user.
     * @param  object  $activity  QuickBooks time activity object.
     * @return void
     */
    private function assertActivityBelongsToUser(User $user, object $activity): void
    {
        $employeeRef = $this->resolveEmployeeRef($user);
        $activityEmployeeRef = $this->extractEmployeeRef($activity);

        if ($activityEmployeeRef !== $employeeRef) {
            abort(response()->json(['message' => 'Time activity not found'], 404));
        }
    }

    /**
     * Extracts the employee reference from an SDK activity object.
     *
     * @param  object  $activity  QuickBooks time activity object.
     * @return string|null
     */
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

    /**
     * Escapes a value for a single-quoted QBO query.
     *
     * @param  string  $value  Raw value to escape for a QBO query.
     * @return string
     */
    private function escapeQueryValue(string $value): string
    {
        return str_replace("'", "\\'", $value);
    }
}
