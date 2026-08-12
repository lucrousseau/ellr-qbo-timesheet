<?php

/**
 * Tests for aggregating approved local entries into one QuickBooks activity.
 */

use App\Enums\TimeEntryStatus;
use App\Jobs\SyncApprovedTimeEntryToQuickBooksJob;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\TimeEntrySyncGroup;
use App\Models\User;
use App\Services\OrganizationTimezoneService;
use App\Services\QboPickerValidationService;
use App\Services\QuickBooksService;
use App\Services\TimeEntryApprovalService;
use App\Services\TimeEntryQboGroupSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use QuickBooksOnline\API\DataService\DataService;

covers(TimeEntryQboGroupSyncService::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    mockQboEntryDisplayNames(['item_name' => 'Programming']);
    config(['app.frontend_admin_url' => 'http://admin.test']);
});

it('pushes sibling approved entries as one quickbooks time activity', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidTimeEntry')->times(3);
    });

    $day = now()->startOfDay()->addHours(9);
    $entries = collect([
        TimeEntry::factory()->forUser($employee)->create([
            'item_ref' => '33',
            'description' => 'Feature A',
            'start_time' => $day->copy(),
            'end_time' => $day->copy()->addHour(),
        ]),
        TimeEntry::factory()->forUser($employee)->create([
            'item_ref' => '33',
            'description' => 'Feature B',
            'start_time' => $day->copy()->addHours(2),
            'end_time' => $day->copy()->addHours(3),
        ]),
        TimeEntry::factory()->forUser($employee)->create([
            'item_ref' => '33',
            'description' => 'Feature C',
            'start_time' => $day->copy()->addHours(4),
            'end_time' => $day->copy()->addHours(5),
        ]),
    ]);

    foreach ($entries as $entry) {
        app(TimeEntryApprovalService::class)->approve($admin, $entry->id);
    }

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '900']);
    $dataService->shouldReceive('FindById')
        ->once()
        ->with('TimeActivity', '900')
        ->andReturn(timeActivityQboEntity('900'));
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    $job = new SyncApprovedTimeEntryToQuickBooksJob(
        $entries[0]->id,
        $employee->id,
        $token->id,
        SyncApprovedTimeEntryToQuickBooksJob::groupKeyFor($entries[0]->fresh(), app(OrganizationTimezoneService::class)),
    );
    $job->handle(app(TimeEntryQboGroupSyncService::class));

    foreach ($entries as $entry) {
        $entry->refresh();
        expect($entry->status)->toBe(TimeEntryStatus::Approved)
            ->and($entry->qbo_id)->toBe('900')
            ->and($entry->sync_group_id)->not->toBeNull();
    }

    $group = TimeEntrySyncGroup::query()->findOrFail($entries[0]->sync_group_id);

    expect($group->member_count)->toBe(3)
        ->and($group->total_duration_seconds)->toBe(10_800)
        ->and($group->qbo_id)->toBe('900')
        ->and($group->qbo_description)->toContain('Ellr grouped time: 3 entries, 3h00.')
        ->and($group->qbo_description)->toContain('Details: http://admin.test/?sync_group='.$group->public_id)
        ->and($group->qbo_description)->toContain('Feature A')
        ->and($group->qbo_description)->toContain('Feature B')
        ->and($group->qbo_description)->toContain('Feature C');

    expect($entries[0]->sync_group_id)->toBe($entries[1]->sync_group_id)
        ->and($entries[1]->sync_group_id)->toBe($entries[2]->sync_group_id);
});

it('does not group entries with a different service item', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $day = now()->startOfDay()->addHours(9);

    $programming = TimeEntry::factory()->forUser($employee)->create([
        'item_ref' => '33',
        'status' => TimeEntryStatus::Approved,
        'reviewed_by_id' => $admin->id,
        'reviewed_at' => now(),
        'start_time' => $day->copy(),
        'end_time' => $day->copy()->addHour(),
    ]);
    TimeEntry::factory()->forUser($employee)->create([
        'item_ref' => '44',
        'status' => TimeEntryStatus::Approved,
        'reviewed_by_id' => $admin->id,
        'reviewed_at' => now(),
        'start_time' => $day->copy()->addHours(2),
        'end_time' => $day->copy()->addHours(3),
    ]);

    $dataService = Mockery::mock(DataService::class);
    $dataService->shouldReceive('Add')->once()->andReturn((object) ['Id' => '901']);
    $dataService->shouldReceive('FindById')
        ->once()
        ->with('TimeActivity', '901')
        ->andReturn(timeActivityQboEntity('901'));
    $dataService->shouldReceive('getLastError')->andReturn(null);

    $this->mock(QuickBooksService::class, function ($mock) use ($dataService) {
        $mock->shouldReceive('dataService')->andReturn($dataService);
    });

    app(TimeEntryQboGroupSyncService::class)->syncApprovedGroup(
        $programming->fresh(['organization']),
        $employee,
        $token,
    );

    expect($programming->refresh()->qbo_id)->toBe('901')
        ->and(TimeEntry::query()->where('item_ref', '44')->value('qbo_id'))->toBeNull();
});
