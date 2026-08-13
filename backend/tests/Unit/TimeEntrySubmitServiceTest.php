<?php

use App\Enums\TimeEntryStatus;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeEntrySubmitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(TimeEntrySubmitService::class);

uses(RefreshDatabase::class);

it('submits a draft entry for approval', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->create();

    $submitted = app(TimeEntrySubmitService::class)->submitForUser($employee, $entry->id);

    expect($submitted->status)->toBe(TimeEntryStatus::Pending);
});

it('resubmits a rejected entry for approval', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->rejected()->create();

    $submitted = app(TimeEntrySubmitService::class)->submitForUser($employee, $entry->id);

    expect($submitted->status)->toBe(TimeEntryStatus::Pending)
        ->and($submitted->rejection_reason)->toBeNull();
});

it('rejects submitting pending entries', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->pending()->create();

    app(TimeEntrySubmitService::class)->submitForUser($employee, $entry->id);
})->throws(HttpResponseException::class);

it('submits all draft entries when ids are omitted', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    TimeEntry::factory()->forUser($employee)->create();
    TimeEntry::factory()->forUser($employee)->create();
    TimeEntry::factory()->forUser($employee)->pending()->create();

    $submitted = app(TimeEntrySubmitService::class)->submitManyForUser($employee);

    expect($submitted)->toHaveCount(2)
        ->and($submitted->every(fn (TimeEntry $entry) => $entry->status === TimeEntryStatus::Pending))->toBeTrue();
});

it('rejects bulk submit when an id is not submittable', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $draft = TimeEntry::factory()->forUser($employee)->create();
    $pending = TimeEntry::factory()->forUser($employee)->pending()->create();

    app(TimeEntrySubmitService::class)->submitManyForUser($employee, [$draft->id, $pending->id]);
})->throws(HttpResponseException::class);
