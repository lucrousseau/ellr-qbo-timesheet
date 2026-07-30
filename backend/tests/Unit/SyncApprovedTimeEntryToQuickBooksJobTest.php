<?php

/**
 * Tests for queued QuickBooks sync after approval.
 */

use App\Enums\TimeEntryStatus;
use App\Jobs\SyncApprovedTimeEntryToQuickBooksJob;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\QuickBooksService;
use App\Services\TimeActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use QuickBooksOnline\API\DataService\DataService;

covers(SyncApprovedTimeEntryToQuickBooksJob::class);

uses(RefreshDatabase::class);

it('stores the quickbooks identifier for an approved entry', function () {
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

    $job = new SyncApprovedTimeEntryToQuickBooksJob(
        $entry->id,
        $employee->id,
        $token->id,
        [
            'description' => $entry->description,
            'is_billable' => $entry->is_billable,
            'start_time' => $entry->start_time?->toIso8601String(),
            'end_time' => $entry->end_time?->toIso8601String(),
        ],
    );

    $job->handle(app(TimeActivityService::class));

    $entry->refresh();

    expect($entry->status)->toBe(TimeEntryStatus::Approved)
        ->and($entry->qbo_id)->toBe('88');
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

    $job = new SyncApprovedTimeEntryToQuickBooksJob(
        $entry->id,
        $employee->id,
        $token->id,
        ['description' => $entry->description, 'start_time' => $entry->start_time?->toIso8601String(), 'end_time' => $entry->end_time?->toIso8601String()],
    );

    expect(fn () => $job->handle(app(TimeActivityService::class)))
        ->toThrow(RuntimeException::class);

    $entry->refresh();

    expect($entry->status)->toBe(TimeEntryStatus::Approved)
        ->and($entry->reviewed_by_id)->toBe($admin->id)
        ->and($entry->qbo_id)->toBeNull();
});
