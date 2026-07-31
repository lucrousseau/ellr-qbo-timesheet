<?php

/**
 * Business logic for creating and managing QuickBooks time activities.
 */

namespace App\Services;

use App\Models\QuickBooksToken;
use App\Models\User;
use App\Support\TimeActivityRefPayload;
use App\Support\TimeActivityTimeValidation;
use App\Support\TimeActivityWriteResponse;
use App\Support\UsesQuickBooksEmployeeScope;
use QuickBooksOnline\API\Facades\TimeActivity;

/**
 * Maps validated input to QBO TimeActivity entities for the linked employee.
 */
class TimeActivityService
{
    use UsesQuickBooksEmployeeScope {
        __construct as private initializeQuickBooksEmployeeScope;
    }

    /**
     * @param  QuickBooksService  $quickBooks  QuickBooks service instance.
     * @param  QboEmployeeAuthorizationService  $employeeAuthorization  QBO employee ownership checks.
     * @param  QuickBooksApiErrorFormatterService  $apiErrors  QuickBooks API error JSON formatter.
     * @param  TimeActivitySnapshotService  $snapshots  Local snapshot persistence.
     * @param  TimeActivitySyncService  $sync  Fallback single-entity sync when write responses are sparse.
     * @param  QboPickerDisplayNameService  $displayNames  Cached QuickBooks label resolver.
     */
    public function __construct(
        QuickBooksService $quickBooks,
        QboEmployeeAuthorizationService $employeeAuthorization,
        QuickBooksApiErrorFormatterService $apiErrors,
        private readonly TimeActivitySnapshotService $snapshots,
        private readonly TimeActivitySyncService $sync,
        private readonly QboPickerDisplayNameService $displayNames,
    ) {
        $this->initializeQuickBooksEmployeeScope($quickBooks, $employeeAuthorization, $apiErrors);
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
        $employeeRef = $this->employeeAuthorization->resolveEmployeeRef($user);
        $dataService = $this->quickBooks->dataService($token);

        $employeePayload = ['value' => $employeeRef];
        $employeeName = $this->displayNames->employeeDisplayName($token, $employeeRef);

        if ($employeeName !== null && $employeeName !== '') {
            $employeePayload['name'] = $employeeName;
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

        $customerRef = TimeActivityRefPayload::customerRef($validated);

        if ($customerRef !== null) {
            $timeActivityPayload['CustomerRef'] = $customerRef;
        }

        $itemRef = TimeActivityRefPayload::itemRef($validated);

        if ($itemRef !== null) {
            $timeActivityPayload['ItemRef'] = $itemRef;
        }

        if (array_key_exists('is_billable', $validated)) {
            $timeActivityPayload['BillableStatus'] = $validated['is_billable'] ? 'Billable' : 'NotBillable';
        }

        $timeActivity = TimeActivity::create($timeActivityPayload);
        $result = $dataService->Add($timeActivity);

        if ($error = $dataService->getLastError()) {
            abort($this->apiErrors->jsonResponse($error));
        }

        TimeActivityWriteResponse::persistSnapshot($this->snapshots, $this->sync, $token, $dataService, $result);

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
        $updatingTimeFields = array_key_exists('start_time', $validated) || array_key_exists('end_time', $validated);

        TimeActivityTimeValidation::abortWhenUpdateTimesIncomplete(
            $updatingTimeFields,
            TimeActivityTimeValidation::normalizeTime($startTime),
            TimeActivityTimeValidation::normalizeTime($endTime),
        );

        TimeActivityTimeValidation::abortUnlessEndAfterStart(
            TimeActivityTimeValidation::normalizeTime($startTime),
            TimeActivityTimeValidation::normalizeTime($endTime),
        );

        $payload = [
            'Id' => $id,
            'SyncToken' => $existing->SyncToken, // @pest-mutate-ignore QBO Facade::update merges $existing when SyncToken is omitted from $payload
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

        if (array_key_exists('is_billable', $validated)) {
            $payload['BillableStatus'] = $validated['is_billable'] ? 'Billable' : 'NotBillable';
        }

        $timeActivity = TimeActivity::update($existing, $payload);
        $result = $dataService->Update($timeActivity);

        if ($error = $dataService->getLastError()) {
            abort($this->apiErrors->jsonResponse($error));
        }

        $merged = TimeActivityWriteResponse::merge($existing, $result);
        TimeActivityWriteResponse::persistSnapshot($this->snapshots, $this->sync, $token, $dataService, $merged);

        return $merged;
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
            abort($this->apiErrors->jsonResponse($error));
        }

        $this->snapshots->softDeleteByQboId($token->realm_id, $id);
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
            abort($this->apiErrors->jsonResponse($error));
        }

        if (! $activity) {
            abort(response()->json(['message' => __('api.time_activity_not_found')], 404));
        }

        $this->employeeAuthorization->assertActivityBelongsToUser($user, $activity);

        return $activity;
    }
}
