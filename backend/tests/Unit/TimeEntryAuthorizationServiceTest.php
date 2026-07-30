<?php

use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeEntryAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;

covers(TimeEntryAuthorizationService::class);

uses(RefreshDatabase::class);

it('allows administrators to review entries in their organization', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $entry = TimeEntry::factory()->forUser($employee)->create();

    app(TimeEntryAuthorizationService::class)->assertCanReview($admin, $entry);

    expect(true)->toBeTrue();
});

it('allows supervisors to review direct report entries', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->create(['organization_id' => $admin->organization_id]);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'supervisor_id' => $supervisor->id,
    ]);
    $entry = TimeEntry::factory()->forUser($employee)->create();

    app(TimeEntryAuthorizationService::class)->assertCanReview($supervisor, $entry);

    expect(true)->toBeTrue();
});

it('forbids employees from reviewing their own entries', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->create();

    app(TimeEntryAuthorizationService::class)->assertCanReview($employee, $entry);
})->throws(HttpResponseException::class);

it('forbids unrelated employees from reviewing entries', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $otherEmployee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '9',
    ]);
    $entry = TimeEntry::factory()->forUser($employee)->create();

    app(TimeEntryAuthorizationService::class)->assertCanReview($otherEmployee, $entry);
})->throws(HttpResponseException::class);

it('forbids assigning a user as their own supervisor', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    app(TimeEntryAuthorizationService::class)->assertCanAssignSupervisor($admin, $employee, $employee);
})->throws(HttpResponseException::class);

it('allows clearing a supervisor assignment', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    app(TimeEntryAuthorizationService::class)->assertCanAssignSupervisor($admin, $employee, null);

    expect(true)->toBeTrue();
});

it('allows administrators to assign supervisors in the same organization', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $supervisor = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '9',
    ]);

    app(TimeEntryAuthorizationService::class)->assertCanAssignSupervisor($admin, $employee, $supervisor);

    expect(true)->toBeTrue();
});

it('forbids supervisors from reviewing entries outside their direct reports', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->create(['organization_id' => $admin->organization_id]);
    $employee = User::factory()->create(['organization_id' => $admin->organization_id]);
    $entry = TimeEntry::factory()->forUser($employee)->create();

    app(TimeEntryAuthorizationService::class)->assertCanReview($supervisor, $entry);
})->throws(HttpResponseException::class);
