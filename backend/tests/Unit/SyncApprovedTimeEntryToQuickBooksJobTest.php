<?php

/**
 * Tests for queued QuickBooks sync after approval.
 */

use App\Enums\TimeEntryStatus;
use App\Jobs\SyncApprovedTimeEntryToQuickBooksJob;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\OrganizationTimezoneService;
use App\Services\QuickBooksService;
use App\Services\TimeEntryQboGroupSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use QuickBooksOnline\API\DataService\DataService;

covers(SyncApprovedTimeEntryToQuickBooksJob::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    mockQboEntryDisplayNames();
});

it('stores the quickbooks identifier for an approved entry', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'status' => TimeEntryStatus::Approved,
        'group_for_qbo' => false,
        'reviewed_by_id' => $admin->id,
        'reviewed_at' => now(),
        'start_time' => now()->subHour(),
        'end_time' => now(),
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

    $groupKey = SyncApprovedTimeEntryToQuickBooksJob::groupKeyFor(
        $entry->fresh(['organization']),
        app(OrganizationTimezoneService::class),
    );
    $job = new SyncApprovedTimeEntryToQuickBooksJob(
        $entry->id,
        $employee->id,
        $token->id,
        $groupKey,
    );

    $job->handle(app(TimeEntryQboGroupSyncService::class));

    $entry->refresh();

    expect($entry->status)->toBe(TimeEntryStatus::Approved)
        ->and($entry->qbo_id)->toBe('88')
        ->and($entry->sync_group_id)->not->toBeNull();
});

it('keeps the approval decision when quickbooks synchronization fails', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'status' => TimeEntryStatus::Approved,
        'reviewed_by_id' => $admin->id,
        'reviewed_at' => now(),
        'start_time' => now()->subHour(),
        'end_time' => now(),
    ]);

    $this->mock(QuickBooksService::class, function ($mock) {
        $mock->shouldReceive('dataService')->andThrow(new RuntimeException('QBO unavailable'));
    });

    $groupKey = SyncApprovedTimeEntryToQuickBooksJob::groupKeyFor(
        $entry->fresh(['organization']),
        app(OrganizationTimezoneService::class),
    );
    $job = new SyncApprovedTimeEntryToQuickBooksJob(
        $entry->id,
        $employee->id,
        $token->id,
        $groupKey,
    );

    expect(fn () => $job->handle(app(TimeEntryQboGroupSyncService::class)))
        ->toThrow(RuntimeException::class);

    $entry->refresh();

    expect($entry->status)->toBe(TimeEntryStatus::Approved)
        ->and($entry->reviewed_by_id)->toBe($admin->id)
        ->and($entry->qbo_id)->toBeNull();
});

it('skips sync when prerequisites are missing or the entry is already linked', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $approved = TimeEntry::factory()->forUser($employee)->create([
        'status' => TimeEntryStatus::Approved,
        'reviewed_by_id' => $admin->id,
        'reviewed_at' => now(),
    ]);
    $linked = TimeEntry::factory()->forUser($employee)->approved('55')->create([
        'reviewed_by_id' => $admin->id,
        'reviewed_at' => now(),
    ]);
    $pending = TimeEntry::factory()->forUser($employee)->create();

    $this->mock(QuickBooksService::class, function ($mock) {
        $mock->shouldReceive('dataService')->never();
    });

    $groupKey = 'noop-group';

    (new SyncApprovedTimeEntryToQuickBooksJob(999_999, $employee->id, $token->id, $groupKey))
        ->handle(app(TimeEntryQboGroupSyncService::class));
    (new SyncApprovedTimeEntryToQuickBooksJob($approved->id, 999_999, $token->id, $groupKey))
        ->handle(app(TimeEntryQboGroupSyncService::class));
    (new SyncApprovedTimeEntryToQuickBooksJob($approved->id, $employee->id, 999_999, $groupKey))
        ->handle(app(TimeEntryQboGroupSyncService::class));
    (new SyncApprovedTimeEntryToQuickBooksJob($pending->id, $employee->id, $token->id, $groupKey))
        ->handle(app(TimeEntryQboGroupSyncService::class));
    (new SyncApprovedTimeEntryToQuickBooksJob($linked->id, $employee->id, $token->id, $groupKey))
        ->handle(app(TimeEntryQboGroupSyncService::class));

    expect($linked->refresh()->qbo_id)->toBe('55');
});

it('exposes a stable unique id for group coalescing', function () {
    $job = new SyncApprovedTimeEntryToQuickBooksJob(1, 2, 3, 'org|user|date|c|p|i|1');

    expect($job->uniqueId())->toBe('time-entry-sync-group:org|user|date|c|p|i|1')
        ->and($job->uniqueFor)->toBe(60);
});

it('loads the organization relation when building a group key', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'item_ref' => '33',
        'start_time' => now()->startOfDay()->addHours(9),
        'end_time' => now()->startOfDay()->addHours(10),
    ]);
    $entry = TimeEntry::query()->findOrFail($entry->id);

    expect($entry->relationLoaded('organization'))->toBeFalse();

    $groupKey = SyncApprovedTimeEntryToQuickBooksJob::groupKeyFor(
        $entry,
        app(OrganizationTimezoneService::class),
    );

    expect($groupKey)->toContain((string) $employee->organization_id)
        ->and($entry->relationLoaded('organization'))->toBeTrue();
});

it('logs permanent queue failures without reverting approval', function () {
    Log::spy();

    $job = new SyncApprovedTimeEntryToQuickBooksJob(12, 34, 56, 'group-key');

    $job->failed(new RuntimeException('queue worker died'));

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context): bool {
            return str_contains($message, 'failed permanently')
                && ($context['time_entry_id'] ?? null) === 12
                && ($context['employee_id'] ?? null) === 34
                && ($context['group_key'] ?? null) === 'group-key';
        });
});
