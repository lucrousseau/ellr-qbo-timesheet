<?php

/**
 * Persists and lists local time entries before QuickBooks synchronization.
 */

namespace App\Services;

use App\Enums\TimeEntryStatus;
use App\Models\TimeEntry;
use App\Models\User;

/**
 * Manages the local time entry write model for employees.
 */
class TimeEntryService
{
    /**
     * Injects picker validation and employee authorization for new entries.
     *
     * @param  QboEmployeeAuthorizationService  $employeeAuthorization  QBO employee ownership checks.
     * @param  QboPickerValidationService  $pickerValidation  QuickBooks picker reference validator.
     * @param  QuickBooksTokenResolverService  $tokenResolver  Resolves organization QBO token.
     */
    public function __construct(
        private readonly QboEmployeeAuthorizationService $employeeAuthorization,
        private readonly QboPickerValidationService $pickerValidation,
        private readonly QuickBooksTokenResolverService $tokenResolver,
    ) {}

    /**
     * Creates a pending time entry for the authenticated employee.
     *
     * @param  User  $user  Employee logging time.
     * @param  array<string, mixed>  $validated  Validated request payload.
     * @return TimeEntry
     */
    public function createForUser(User $user, array $validated): TimeEntry
    {
        $this->employeeAuthorization->resolveEmployeeRef($user);

        if ($this->hasPickerValues($validated)) {
            $token = $this->tokenResolver->resolve($user);
            $this->pickerValidation->assertValidTimeEntrySelections($user, $token, $validated);
        }

        return TimeEntry::query()->create($this->attributesFromValidated($user, $validated));
    }

    /**
     * Updates a pending time entry owned by the employee.
     *
     * @param  User  $user  Entry owner.
     * @param  int  $id  Local time entry identifier.
     * @param  array<string, mixed>  $validated  Validated update payload.
     * @return TimeEntry
     */
    public function updateForUser(User $user, int $id, array $validated): TimeEntry
    {
        $entry = $this->findOwnedEntry($user, $id);
        $this->assertEditable($entry);

        $entry->fill($this->updateAttributesFromValidated($validated));

        if ($this->containsPickerFields($validated) && $this->hasPickerValues([
            'customer_ref' => $entry->customer_ref,
            'project_ref' => $entry->project_ref,
            'item_ref' => $entry->item_ref,
        ])) {
            $token = $this->tokenResolver->resolve($user);
            $this->pickerValidation->assertValidTimeEntrySelections($user, $token, [
                'customer_ref' => $entry->customer_ref,
                'project_ref' => $entry->project_ref,
                'item_ref' => $entry->item_ref,
            ]);
        }

        $entry->save();

        return $entry->refresh();
    }

    /**
     * Deletes a pending or rejected time entry owned by the employee.
     *
     * @param  User  $user  Entry owner.
     * @param  int  $id  Local time entry identifier.
     * @return void
     */
    public function deleteForUser(User $user, int $id): void
    {
        $entry = $this->findOwnedEntry($user, $id);

        if (! in_array($entry->status, [TimeEntryStatus::Pending, TimeEntryStatus::Rejected], true)) {
            abort(response()->json([
                'error' => 'time_entry_not_deletable',
                'message' => __('api.time_entry_not_deletable'),
            ], 422));
        }

        $entry->delete();
    }

    /**
     * Loads a time entry owned by the employee.
     *
     * @param  User  $user  Entry owner.
     * @param  int  $id  Local time entry identifier.
     * @return TimeEntry
     */
    public function findOwnedEntry(User $user, int $id): TimeEntry
    {
        return TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOr(function (): never {
                abort(response()->json(['message' => __('api.time_entry_not_found')], 404));
            });
    }

    /**
     * Builds create attributes from validated input.
     *
     * @param  User  $user  Employee logging time.
     * @param  array<string, mixed>  $validated  Validated request payload.
     * @return array<string, mixed>
     */
    private function attributesFromValidated(User $user, array $validated): array
    {
        return [
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
            'customer_ref' => $validated['customer_ref'] ?? null,
            'customer_name' => $validated['customer_name'] ?? null,
            'project_ref' => $validated['project_ref'] ?? null,
            'project_name' => $validated['project_name'] ?? null,
            'item_ref' => $validated['item_ref'] ?? null,
            'item_name' => $validated['item_name'] ?? null,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'description' => $validated['description'] ?? null,
            'is_billable' => (bool) ($validated['is_billable'] ?? false),
            'status' => TimeEntryStatus::Pending,
        ];
    }

    /**
     * Builds update attributes from validated input.
     *
     * @param  array<string, mixed>  $validated  Validated update payload.
     * @return array<string, mixed>
     */
    private function updateAttributesFromValidated(array $validated): array
    {
        $attributes = [];

        foreach ([
            'customer_ref',
            'customer_name',
            'project_ref',
            'project_name',
            'item_ref',
            'item_name',
            'start_time',
            'end_time',
            'description',
        ] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('is_billable', $validated)) {
            $attributes['is_billable'] = (bool) $validated['is_billable'];
        }

        return $attributes;
    }

    /**
     * Aborts when the entry is no longer editable by the employee.
     *
     * @param  TimeEntry  $entry  Entry being mutated.
     * @return void
     */
    private function assertEditable(TimeEntry $entry): void
    {
        if (! $entry->status->isEditable()) {
            abort(response()->json([
                'error' => 'time_entry_not_editable',
                'message' => __('api.time_entry_not_editable'),
            ], 422));
        }
    }

    /**
     * Indicates whether the validated payload includes QuickBooks picker fields.
     *
     * @param  array<string, mixed>  $validated  Validated update payload.
     * @return bool
     */
    private function containsPickerFields(array $validated): bool
    {
        foreach (['customer_ref', 'project_ref', 'item_ref'] as $field) {
            if (array_key_exists($field, $validated)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Indicates whether any QuickBooks picker reference is present.
     *
     * @param  array<string, mixed>  $validated  Validated payload or entry fields.
     * @return bool
     */
    private function hasPickerValues(array $validated): bool
    {
        foreach (['customer_ref', 'project_ref', 'item_ref'] as $field) {
            $value = $validated[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }
}
