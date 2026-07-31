<?php

use App\Enums\TimeEntryStatus;
use App\Jobs\SyncApprovedTimeEntryToQuickBooksJob;
use App\Models\QuickBooksToken;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\QboPickerValidationService;
use App\Services\QuickBooksService;
use App\Services\TimeEntryApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Queue;
use QuickBooksOnline\API\DataService\DataService;

covers(TimeEntryApprovalService::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    mockQboEntryDisplayNames();
});

it('lists only pending entries for administrators', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    TimeEntry::factory()->forUser($employee)->create(['description' => 'Needs review']);
    TimeEntry::factory()->forUser($employee)->approved()->create();

    $response = app(TimeEntryApprovalService::class)->listPendingForReviewer($admin, 1, 10);

    expect($response['data'])->toHaveCount(1)
        ->and($response['data'][0]['description'])->toBe('Needs review')
        ->and($response['meta']['truncated'])->toBeFalse();
});

it('limits pending entries to direct reports for supervisors', function () {
    $admin = User::factory()->admin()->create();
    $supervisor = User::factory()->create(['organization_id' => $admin->organization_id]);
    $directReport = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'supervisor_id' => $supervisor->id,
    ]);
    $otherEmployee = User::factory()->create(['organization_id' => $admin->organization_id]);
    TimeEntry::factory()->forUser($directReport)->create(['description' => 'Direct report']);
    TimeEntry::factory()->forUser($otherEmployee)->create(['description' => 'Other employee']);

    $response = app(TimeEntryApprovalService::class)->listPendingForReviewer($supervisor, 1, 10);

    expect($response['data'])->toHaveCount(1)
        ->and($response['data'][0]['description'])->toBe('Direct report');
});

it('marks pending approval lists as truncated when more rows exist', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    foreach (range(1, 3) as $index) {
        TimeEntry::factory()->forUser($employee)->create([
            'start_time' => now()->subHours($index),
            'end_time' => now()->subHours($index - 1),
        ]);
    }

    $response = app(TimeEntryApprovalService::class)->listPendingForReviewer($admin, 1, 2);

    expect($response['data'])->toHaveCount(2)
        ->and($response['meta']['truncated'])->toBeTrue()
        ->and($response['meta']['count'])->toBe(2);
});

it('rejects a pending entry without syncing to quickbooks', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $entry = TimeEntry::factory()->forUser($employee)->create();

    $rejected = app(TimeEntryApprovalService::class)->reject($admin, $entry->id, 'Wrong client');

    expect($rejected->status)->toBe(TimeEntryStatus::Rejected)
        ->and($rejected->rejection_reason)->toBe('Wrong client')
        ->and($rejected->reviewed_by_id)->toBe($admin->id);
});

it('approves a pending entry and stores the quickbooks identifier', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $entry = TimeEntry::factory()->forUser($employee)->create();

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

    $approved = app(TimeEntryApprovalService::class)->approve($admin, $entry->id);

    expect($approved->status)->toBe(TimeEntryStatus::Approved)
        ->and($approved->qbo_id)->toBe('88');
});

it('dispatches resolved quickbooks labels in the approval sync payload', function () {
    Queue::fake();
    mockQboEntryDisplayNames([
        'customer_name' => 'Acme Corp',
        'project_name' => 'Website redesign',
        'item_name' => 'Consulting',
    ]);

    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
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
        $mock->shouldReceive('assertValidTimeEntry')->once();
    });

    app(TimeEntryApprovalService::class)->approve($admin, $entry->id);

    Queue::assertPushed(
        SyncApprovedTimeEntryToQuickBooksJob::class,
        fn (SyncApprovedTimeEntryToQuickBooksJob $job): bool => $job->payload['customer_name'] === 'Acme Corp'
            && $job->payload['project_name'] === 'Website redesign'
            && $job->payload['item_name'] === 'Consulting',
    );
});

it('validates picker references before approving entries with quickbooks fields', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $entry = TimeEntry::factory()->forUser($employee)->create([
        'customer_ref' => '11',
    ]);

    $this->mock(QboPickerValidationService::class, function ($mock) {
        $mock->shouldReceive('assertValidTimeEntry')->once();
    });

    $this->mock(QuickBooksService::class, function ($mock) {
        $mock->shouldReceive('dataService')->andThrow(new RuntimeException('stop after validation'));
    });

    expect(fn () => app(TimeEntryApprovalService::class)->approve($admin, $entry->id))
        ->toThrow(RuntimeException::class);
});

it('returns not found when approving a non pending entry', function () {
    $admin = User::factory()->admin()->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $entry = TimeEntry::factory()->forUser($employee)->approved()->create();

    app(TimeEntryApprovalService::class)->approve($admin, $entry->id);
})->throws(HttpResponseException::class);

it('keeps approval when quickbooks synchronization fails', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $entry = TimeEntry::factory()->forUser($employee)->create();

    $this->mock(QuickBooksService::class, function ($mock) {
        $mock->shouldReceive('dataService')->andThrow(new RuntimeException('QBO unavailable'));
    });

    expect(fn () => app(TimeEntryApprovalService::class)->approve($admin, $entry->id))
        ->toThrow(RuntimeException::class);

    $entry->refresh();

    expect($entry->status)->toBe(TimeEntryStatus::Approved)
        ->and($entry->reviewed_by_id)->toBe($admin->id)
        ->and($entry->qbo_id)->toBeNull();
});

it('queues quickbooks synchronization after approval', function () {
    Queue::fake();

    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-42']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);
    $entry = TimeEntry::factory()->forUser($employee)->create();

    $approved = app(TimeEntryApprovalService::class)->approve($admin, $entry->id);

    expect($approved->status)->toBe(TimeEntryStatus::Approved)
        ->and($approved->qbo_id)->toBeNull();

    Queue::assertPushed(SyncApprovedTimeEntryToQuickBooksJob::class, function (SyncApprovedTimeEntryToQuickBooksJob $job) use ($entry, $employee): bool {
        $token = QuickBooksToken::query()->where('realm_id', 'realm-42')->first();

        return $job->timeEntryId === $entry->id
            && $job->employeeId === $employee->id
            && $token !== null
            && $job->tokenId === $token->id;
    });
});
