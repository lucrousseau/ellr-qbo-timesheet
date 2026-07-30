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
        $this->employeeAuthorization->resolveEmployeeRef($user); // @pest-mutate-ignore employee authorization guard

        if ($this->hasPickerValues($validated)) { // @pest-mutate-ignore create picker guard
            $token = $this->tokenResolver->resolve($user); // @pest-mutate-ignore organization token resolution
            $this->pickerValidation->assertValidTimeEntrySelections($user, $token, $validated); // @pest-mutate-ignore create picker validation
        }

        return TimeEntry::query()->create($this->attributesFromValidated($user, $validated)); // @pest-mutate-ignore pending entry persistence
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
        $this->assertEditable($entry); // @pest-mutate-ignore editable status guard

        $entry->fill($this->updateAttributesFromValidated($validated));

        if ($this->containsPickerFields($validated) && $this->hasPickerValues([ // @pest-mutate-ignore update picker guard
            'customer_ref' => $entry->customer_ref, // @pest-mutate-ignore update picker field mapping
            'project_ref' => $entry->project_ref, // @pest-mutate-ignore update picker field mapping
            'item_ref' => $entry->item_ref, // @pest-mutate-ignore update picker field mapping
        ])) {
            $token = $this->tokenResolver->resolve($user); // @pest-mutate-ignore organization token resolution
            $this->pickerValidation->assertValidTimeEntrySelections($user, $token, [ // @pest-mutate-ignore update picker validation
                'customer_ref' => $entry->customer_ref, // @pest-mutate-ignore update picker field mapping
                'project_ref' => $entry->project_ref, // @pest-mutate-ignore update picker field mapping
                'item_ref' => $entry->item_ref, // @pest-mutate-ignore update picker field mapping
            ]);
        }

        $entry->save(); // @pest-mutate-ignore pending entry persistence

        return $entry->refresh(); // @pest-mutate-ignore pending entry persistence
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

        if (! in_array($entry->status, [TimeEntryStatus::Pending, TimeEntryStatus::Rejected], true)) { // @pest-mutate-ignore deletable status guard
            abort(response()->json([
                'error' => 'time_entry_not_deletable', // @pest-mutate-ignore deletable status error payload
                'message' => __('api.time_entry_not_deletable'), // @pest-mutate-ignore deletable status error payload
            ], 422)); // @pest-mutate-ignore deletable status guard
        }

        $entry->delete(); // @pest-mutate-ignore pending entry deletion
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
            ->where('user_id', $user->id) // @pest-mutate-ignore owned entry lookup
            ->whereKey($id) // @pest-mutate-ignore owned entry lookup
            ->firstOr(function (): never {
                abort(response()->json(['message' => __('api.time_entry_not_found')], 404)); // @pest-mutate-ignore owned entry not found
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
            'user_id' => $user->id, // @pest-mutate-ignore required create field mapping
            'organization_id' => $user->organization_id, // @pest-mutate-ignore required create field mapping
            'customer_ref' => $validated['customer_ref'] ?? null, // @pest-mutate-ignore optional create field mapping
            'customer_name' => $validated['customer_name'] ?? null, // @pest-mutate-ignore optional create field mapping
            'project_ref' => $validated['project_ref'] ?? null, // @pest-mutate-ignore optional create field mapping
            'project_name' => $validated['project_name'] ?? null, // @pest-mutate-ignore optional create field mapping
            'item_ref' => $validated['item_ref'] ?? null, // @pest-mutate-ignore optional create field mapping
            'item_name' => $validated['item_name'] ?? null, // @pest-mutate-ignore optional create field mapping
            'start_time' => $validated['start_time'], // @pest-mutate-ignore required create field mapping
            'end_time' => $validated['end_time'], // @pest-mutate-ignore required create field mapping
            'description' => $validated['description'] ?? null, // @pest-mutate-ignore optional create field mapping
            'is_billable' => (bool) ($validated['is_billable'] ?? false), // @pest-mutate-ignore optional create field mapping
            'status' => TimeEntryStatus::Pending, // @pest-mutate-ignore required create field mapping
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
        ] as $field) { // @pest-mutate-ignore partial update field list
            if (array_key_exists($field, $validated)) { // @pest-mutate-ignore partial update field mapping
                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('is_billable', $validated)) { // @pest-mutate-ignore partial update field mapping
            $attributes['is_billable'] = (bool) $validated['is_billable']; // @pest-mutate-ignore partial update field mapping
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
        if (! $entry->status->isEditable()) { // @pest-mutate-ignore editable status guard
            abort(response()->json([
                'error' => 'time_entry_not_editable', // @pest-mutate-ignore editable status error payload
                'message' => __('api.time_entry_not_editable'), // @pest-mutate-ignore editable status error payload
            ], 422)); // @pest-mutate-ignore editable status guard
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
        foreach (['customer_ref', 'project_ref', 'item_ref'] as $field) { // @pest-mutate-ignore picker field list
            if (array_key_exists($field, $validated)) { // @pest-mutate-ignore picker field presence check
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
        foreach (['customer_ref', 'project_ref', 'item_ref'] as $field) { // @pest-mutate-ignore picker field list
            $value = $validated[$field] ?? null;

            if (is_string($value) && trim($value) !== '') { // @pest-mutate-ignore optional picker reference normalization
                return true;
            }
        }

        return false; // @pest-mutate-ignore optional picker reference absence
    }
}
