<?php

use App\Enums\TimeEntryStatus;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\QboPickerValidationService;
use App\Services\TimeEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(TimeEntryService::class);

uses(RefreshDatabase::class);

it('requires a configured quickbooks employee before creating entries', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => null]);

    app(TimeEntryService::class)->createForUser($employee, [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
    ]);
})->throws(HttpResponseException::class);

it('persists all optional create fields from validated input', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidTimeEntrySelections')->once();
    });

    $entry = app(TimeEntryService::class)->createForUser($employee, [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
        'description' => 'Notes',
        'customer_ref' => '11',
        'project_ref' => '22',
        'item_ref' => '33',
        'is_billable' => true,
    ]);

    expect($entry->organization_id)->toBe($employee->organization_id)
        ->and($entry->customer_ref)->toBe('11')
        ->and($entry->project_ref)->toBe('22')
        ->and($entry->item_ref)->toBe('33')
        ->and($entry->is_billable)->toBeTrue();
});

it('updates a draft entry owned by the employee', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->create();

    $updated = app(TimeEntryService::class)->updateForUser($employee, $entry->id, [
        'description' => 'Updated notes',
        'is_billable' => true,
    ]);

    expect($updated->description)->toBe('Updated notes')
        ->and($updated->is_billable)->toBeTrue();
});

it('rejects updates to approved entries', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->approved()->create();

    app(TimeEntryService::class)->updateForUser($employee, $entry->id, [
        'description' => 'Too late',
    ]);
})->throws(HttpResponseException::class);

it('deletes draft and rejected entries', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $draft = TimeEntry::factory()->forUser($employee)->create();
    $rejected = TimeEntry::factory()->forUser($employee)->rejected()->create();

    $service = app(TimeEntryService::class);
    $service->deleteForUser($employee, $draft->id);
    $service->deleteForUser($employee, $rejected->id);

    expect(TimeEntry::query()->whereKey([$draft->id, $rejected->id])->exists())->toBeFalse();
});

it('rejects deleting pending entries', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->pending()->create();

    app(TimeEntryService::class)->deleteForUser($employee, $entry->id);
})->throws(HttpResponseException::class);

it('rejects updates to pending entries by the employee', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->pending()->create();

    app(TimeEntryService::class)->updateForUser($employee, $entry->id, [
        'description' => 'Too late',
    ]);
})->throws(HttpResponseException::class);

it('creates entries as draft', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);

    $entry = app(TimeEntryService::class)->createForUser($employee, [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
    ]);

    expect($entry->status)->toBe(TimeEntryStatus::Draft);
});

it('rejects deleting approved entries', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->approved()->create();

    app(TimeEntryService::class)->deleteForUser($employee, $entry->id);
})->throws(HttpResponseException::class);

it('returns not found when the entry is not owned by the employee', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $otherEmployee = User::factory()->create(['qbo_employee_ref' => '9']);
    $entry = TimeEntry::factory()->forUser($otherEmployee)->create();

    app(TimeEntryService::class)->findOwnedEntry($employee, $entry->id);
})->throws(HttpResponseException::class);

it('creates a time entry with picker fields and validates selections', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidTimeEntrySelections')->once();
    });

    $entry = app(TimeEntryService::class)->createForUser($employee, [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
        'customer_ref' => '11',
        'project_ref' => '22',
        'item_ref' => '33',
        'is_billable' => true,
    ]);

    expect($entry->customer_ref)->toBe('11')
        ->and($entry->project_ref)->toBe('22')
        ->and($entry->item_ref)->toBe('33')
        ->and($entry->is_billable)->toBeTrue();
});

it('skips picker validation when no picker references are provided', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldNotReceive('assertValidTimeEntrySelections');
    });

    $entry = app(TimeEntryService::class)->createForUser($employee, [
        'start_time' => '2026-07-27T09:00:00',
        'end_time' => '2026-07-27T17:00:00',
    ]);

    expect($entry->customer_ref)->toBeNull()
        ->and($entry->project_ref)->toBeNull();
});

it('updates picker fields and revalidates selections when references remain', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'customer_ref' => '11',
        'project_ref' => '22',
        'item_ref' => '33',
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidTimeEntrySelections')->once();
    });

    $updated = app(TimeEntryService::class)->updateForUser($employee, $entry->id, [
        'customer_ref' => '12',
    ]);

    expect($updated->customer_ref)->toBe('12');
});

it('updates time range and description fields', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->create();

    $updated = app(TimeEntryService::class)->updateForUser($employee, $entry->id, [
        'start_time' => '2026-07-28T09:00:00',
        'end_time' => '2026-07-28T12:00:00',
        'description' => 'Extended session',
    ]);

    expect($updated->description)->toBe('Extended session')
        ->and($updated->start_time?->toDateTimeString())->toBe('2026-07-28 09:00:00')
        ->and($updated->end_time?->toDateTimeString())->toBe('2026-07-28 12:00:00');
});

it('does not validate picker selections when update clears all references', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'customer_ref' => '11',
        'project_ref' => '22',
        'item_ref' => '33',
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldNotReceive('assertValidTimeEntrySelections');
    });

    $updated = app(TimeEntryService::class)->updateForUser($employee, $entry->id, [
        'customer_ref' => null,
        'project_ref' => null,
        'item_ref' => null,
    ]);

    expect($updated->customer_ref)->toBeNull()
        ->and($updated->project_ref)->toBeNull()
        ->and($updated->item_ref)->toBeNull();
});
