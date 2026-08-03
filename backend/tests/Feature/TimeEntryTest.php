<?php

use App\Http\Controllers\Api\AdminUserSupervisorController;
use App\Http\Controllers\Api\TimeEntryApprovalController;
use App\Http\Controllers\Api\TimeEntryController;
use App\Http\Requests\ListTimeEntryRequest;
use App\Http\Requests\RejectTimeEntryRequest;
use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Models\Organization;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\QuickBooksService;
use App\Services\TimeEntryApprovalService;
use App\Services\TimeEntryAuthorizationService;
use App\Services\TimeEntryListService;
use App\Services\TimeEntryService;
use App\Services\UserSupervisorService;
use App\Support\TimeEntryApiResponse;
use QuickBooksOnline\API\DataService\DataService;

covers(TimeEntryController::class);
covers(TimeEntryApprovalController::class);
covers(AdminUserSupervisorController::class);
covers(TimeEntryListService::class);
covers(TimeEntryService::class);
covers(TimeEntryApprovalService::class);
covers(TimeEntryAuthorizationService::class);
covers(UserSupervisorService::class);
covers(TimeEntryApiResponse::class);
covers(StoreTimeEntryRequest::class);
covers(UpdateTimeEntryRequest::class);
covers(ListTimeEntryRequest::class);
covers(RejectTimeEntryRequest::class);

it('requires authentication for time entries', function () {
    $this->getJson('/api/time-entries')->assertUnauthorized();
});

describe('employee time entries', function () {
    beforeEach(function () {
        mockQboEntryDisplayNames();
        actingAsWithQboEmployee();
    });

    it('creates a pending time entry', function () {
        $this->postJson('/api/time-entries', [
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
            'description' => 'Draft work',
            'ticket_key' => 'ELLR-7',
            'ticket_source' => 'jira',
            'ticket_url' => 'https://example.atlassian.net/browse/ELLR-7',
            'ticket_title' => 'Draft ticket',
        ], frontendHeaders())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.description', 'Draft work')
            ->assertJsonPath('data.ticket_key', 'ELLR-7')
            ->assertJsonPath('data.ticket_source', 'jira')
            ->assertJsonPath('data.ticket_url', 'https://example.atlassian.net/browse/ELLR-7')
            ->assertJsonPath('data.ticket_title', 'Draft ticket');
    });

    it('lists time entries for the signed-in employee', function () {
        $user = auth()->user();
        TimeEntry::factory()->forUser($user)->create([
            'description' => 'Listed entry',
        ]);

        $this->getJson('/api/time-entries', frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.description', 'Listed entry')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.list_id', fn (string $value) => str_starts_with($value, 'local:'));
    });

    it('includes legacy quickbooks snapshots as approved entries', function () {
        $admin = actingAsAdmin();
        $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-legacy']);
        $employee = timesheetUserFor($admin);
        $this->actingAs($employee);

        seedListedTimeActivities($token, '7', 1, 42);
        $localEntry = TimeEntry::factory()->forUser($employee)->create([
            'description' => 'Pending local only',
            'start_time' => '2020-01-01 09:00:00',
            'end_time' => '2020-01-01 10:00:00',
        ]);

        $response = $this->getJson('/api/time-entries', frontendHeaders())
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $listIds = collect($response->json('data'))->pluck('list_id')->all();
        expect($listIds)->toContain('local:'.$localEntry->id)
            ->and($listIds)->toContain('qbo:42');

        $qboRow = collect($response->json('data'))->firstWhere('list_id', 'qbo:42');
        expect($qboRow['status'])->toBe('approved')
            ->and($qboRow['qbo_id'])->toBe('42');
    });

    it('does not duplicate approved local entries already linked to quickbooks', function () {
        $admin = actingAsAdmin();
        $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-dedupe']);
        $employee = timesheetUserFor($admin);
        $this->actingAs($employee);

        seedListedTimeActivities($token, '7', 1, 99);
        $localEntry = TimeEntry::factory()->forUser($employee)->approved('99')->create([
            'description' => 'Already linked',
        ]);

        $this->getJson('/api/time-entries', frontendHeaders())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.list_id', 'local:'.$localEntry->id);
    });

    it('updates a pending time entry', function () {
        $user = auth()->user();
        $entry = TimeEntry::factory()->forUser($user)->create();

        $this->patchJson("/api/time-entries/{$entry->id}", [
            'description' => 'Updated notes',
            'ticket_key' => 'LIN-3',
            'ticket_source' => 'linear',
            'ticket_title' => 'Updated ticket',
        ], frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.description', 'Updated notes')
            ->assertJsonPath('data.ticket_key', 'LIN-3')
            ->assertJsonPath('data.ticket_source', 'linear')
            ->assertJsonPath('data.ticket_title', 'Updated ticket');
    });

    it('rejects updates to approved entries', function () {
        $user = auth()->user();
        $entry = TimeEntry::factory()->forUser($user)->approved()->create();

        $this->patchJson("/api/time-entries/{$entry->id}", [
            'description' => 'Too late',
        ], frontendHeaders())
            ->assertUnprocessable()
            ->assertJsonPath('error', 'time_entry_not_editable');
    });

    it('deletes a pending time entry', function () {
        $user = auth()->user();
        $entry = TimeEntry::factory()->forUser($user)->create();

        $this->deleteJson("/api/time-entries/{$entry->id}", [], frontendHeaders())
            ->assertNoContent();

        expect(TimeEntry::query()->whereKey($entry->id)->exists())->toBeFalse();
    });
});

describe('time entry approvals', function () {
    beforeEach(function () {
        mockQboEntryDisplayNames();
    });

    it('lets an administrator approve a pending entry and sync to quickbooks', function () {
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
        $employee = timesheetUserFor($admin, ['supervisor_id' => null]);
        $entry = TimeEntry::factory()->forUser($employee)->create([
            'start_time' => '2026-07-27T09:00:00',
            'end_time' => '2026-07-27T17:00:00',
            'description' => 'Ready for approval',
        ]);

        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '88']);
        $dataService->shouldReceive('FindById')
            ->once()
            ->with('TimeActivity', '88')
            ->andReturn(timeActivityQboEntity('88'));
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->postJson("/api/admin/time-entry-approvals/{$entry->id}/approve", [], frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.qbo_id', '88');
    });

    it('lets a supervisor approve direct report entries', function () {
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
        $supervisor = timesheetUserFor($admin, ['qbo_employee_ref' => '9']);
        $employee = timesheetUserFor($admin, ['supervisor_id' => $supervisor->id]);
        $entry = TimeEntry::factory()->forUser($employee)->create();

        $dataService = Mockery::mock(DataService::class);
        $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '77']);
        $dataService->shouldReceive('FindById')
            ->once()
            ->with('TimeActivity', '77')
            ->andReturn(timeActivityQboEntity('77', '7'));
        $dataService->shouldReceive('getLastError')->andReturn(null);

        $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
            $mock->shouldReceive('dataService')->andReturn($dataService);
        });

        $this->actingAs($supervisor)
            ->postJson("/api/time-entry-approvals/{$entry->id}/approve", [], frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    });

    it('rejects approval from unrelated employees', function () {
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
        $employee = timesheetUserFor($admin);
        $otherEmployee = timesheetUserFor($admin, ['qbo_employee_ref' => '9']);
        $entry = TimeEntry::factory()->forUser($employee)->create();

        $this->actingAs($otherEmployee)
            ->postJson("/api/time-entry-approvals/{$entry->id}/approve", [], frontendHeaders())
            ->assertForbidden()
            ->assertJsonPath('error', 'time_entry_review_forbidden');
    });

    it('rejects a pending entry without syncing to quickbooks', function () {
        $admin = actingAsAdmin();
        $employee = timesheetUserFor($admin);
        $entry = TimeEntry::factory()->forUser($employee)->create();

        $this->actingAs($admin)
            ->postJson("/api/admin/time-entry-approvals/{$entry->id}/reject", [
                'reason' => 'Wrong client',
            ], frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Wrong client')
            ->assertJsonPath('data.qbo_id', null);
    });

    it('lists pending entries for administrators', function () {
        $admin = actingAsAdmin();
        $employee = timesheetUserFor($admin);
        TimeEntry::factory()->forUser($employee)->create(['description' => 'Needs review']);
        TimeEntry::factory()->forUser($employee)->approved()->create();

        $this->getJson('/api/admin/time-entry-approvals', frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.0.description', 'Needs review')
            ->assertJsonCount(1, 'data');
    });
});

describe('supervisor assignment', function () {
    it('lets an administrator assign a supervisor to a timesheet user', function () {
        $admin = actingAsAdmin();
        $employee = timesheetUserFor($admin, ['qbo_employee_ref' => '7']);
        $supervisor = timesheetUserFor($admin, ['qbo_employee_ref' => '9']);

        $this->patchJson("/api/admin/users/{$employee->id}/supervisor", [
            'supervisor_id' => $supervisor->id,
        ], frontendHeaders())
            ->assertOk()
            ->assertJsonPath('data.supervisor_id', $supervisor->id);
    });
});

describe('time entry approval security', function () {
    it('forbids administrators from approving entries in another organization', function () {
        $admin = actingAsAdmin();
        QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-home']);

        $foreignOrganization = Organization::factory()->create();
        $foreignEmployee = User::factory()->create([
            'organization_id' => $foreignOrganization->id,
            'qbo_employee_ref' => '88',
        ]);
        $entry = TimeEntry::factory()->forUser($foreignEmployee)->create();

        $this->postJson("/api/admin/time-entry-approvals/{$entry->id}/approve", [], frontendHeaders())
            ->assertNotFound();
    });
});
