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

it('resubmits a rejected entry and clears review metadata', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $reviewer = User::factory()->create(['organization_id' => $employee->organization_id]);
    $entry = TimeEntry::factory()->forUser($employee)->rejected()->create();
    $entry->forceFill([
        'reviewed_by_id' => $reviewer->id,
        'reviewed_at' => now()->subDay(),
    ])->save();

    $submitted = app(TimeEntrySubmitService::class)->submitForUser($employee, $entry->id);

    expect($submitted->status)->toBe(TimeEntryStatus::Pending)
        ->and($submitted->rejection_reason)->toBeNull()
        ->and($submitted->reviewed_by_id)->toBeNull()
        ->and($submitted->reviewed_at)->toBeNull();
});

it('rejects submitting pending entries with a not submittable payload', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->pending()->create();

    try {
        app(TimeEntrySubmitService::class)->submitForUser($employee, $entry->id);
        $this->fail('Expected HttpResponseException');
    } catch (HttpResponseException $exception) {
        $response = $exception->getResponse();
        expect($response->getStatusCode())->toBe(422)
            ->and($response->getData(true))->toMatchArray([
                'error' => 'time_entry_not_submittable',
            ]);
    }
});

it('submits draft and rejected entries when ids are omitted', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    TimeEntry::factory()->forUser($employee)->create();
    TimeEntry::factory()->forUser($employee)->rejected()->create();
    TimeEntry::factory()->forUser($employee)->pending()->create();

    $submitted = app(TimeEntrySubmitService::class)->submitManyForUser($employee);

    expect($submitted)->toHaveCount(2)
        ->and($submitted->every(fn (TimeEntry $entry) => $entry->status === TimeEntryStatus::Pending))->toBeTrue();
});

it('submits only the requested draft ids', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $first = TimeEntry::factory()->forUser($employee)->create();
    $second = TimeEntry::factory()->forUser($employee)->create();

    $submitted = app(TimeEntrySubmitService::class)->submitManyForUser($employee, [$first->id]);

    expect($submitted)->toHaveCount(1)
        ->and($submitted->first()?->id)->toBe($first->id)
        ->and($second->fresh()->status)->toBe(TimeEntryStatus::Draft);
});

it('accepts duplicate ids when submitting a single submittable entry', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $draft = TimeEntry::factory()->forUser($employee)->create();

    $submitted = app(TimeEntrySubmitService::class)->submitManyForUser($employee, [$draft->id, $draft->id]);

    expect($submitted)->toHaveCount(1)
        ->and($submitted->first()?->status)->toBe(TimeEntryStatus::Pending);
});

it('rejects bulk submit when an id is not submittable', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $draft = TimeEntry::factory()->forUser($employee)->create();
    $pending = TimeEntry::factory()->forUser($employee)->pending()->create();

    try {
        app(TimeEntrySubmitService::class)->submitManyForUser($employee, [$draft->id, $pending->id]);
        $this->fail('Expected HttpResponseException');
    } catch (HttpResponseException $exception) {
        $response = $exception->getResponse();
        expect($response->getStatusCode())->toBe(422)
            ->and($response->getData(true)['error'] ?? null)->toBe('time_entry_not_submittable')
            ->and($response->getData(true)['message'] ?? null)->not->toBe('');
    }
});
