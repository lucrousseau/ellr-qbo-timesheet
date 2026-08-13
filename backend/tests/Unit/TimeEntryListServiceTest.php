<?php

use App\Models\QuickBooksToken;
use App\Models\TimeActivitySnapshot;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\TimeEntryListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

covers(TimeEntryListService::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-07-29 12:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('lists local entries for an employee without a quickbooks token', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    TimeEntry::factory()->forUser($employee)->create([
        'description' => 'Local only',
        'start_time' => '2026-07-28 09:00:00',
        'end_time' => '2026-07-28 10:00:00',
    ]);

    $result = app(TimeEntryListService::class)->listForUser($employee, null, 1, 25);

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['description'])->toBe('Local only')
        ->and($result['data'][0]['list_id'])->toStartWith('local:')
        ->and($result['meta']['truncated'])->toBeFalse();
});

it('merges legacy quickbooks snapshots with local entries', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-merge']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    seedListedTimeActivities($token, '7', 1, 42);
    TimeEntry::factory()->forUser($employee)->create([
        'description' => 'Pending local',
        'start_time' => '2020-01-01 09:00:00',
        'end_time' => '2020-01-01 10:00:00',
    ]);

    $result = app(TimeEntryListService::class)->listForUser($employee, $token, 1, 25);

    $listIds = collect($result['data'])->pluck('list_id')->all();
    expect($listIds)->toContain('qbo:42')
        ->and(collect($listIds)->first(fn (string $id): bool => str_starts_with($id, 'local:')))->not->toBeNull();
});

it('resolves display names for legacy snapshots that only store refs', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-labels']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    TimeActivitySnapshot::factory()
        ->forRealm($token->realm_id)
        ->forEmployee('7')
        ->create([
            'qbo_id' => '88',
            'customer_ref' => '11',
            'customer_name' => null,
            'project_ref' => '22',
            'project_name' => null,
            'item_ref' => '33',
            'item_name' => null,
            'start_time' => now()->subHour(),
            'end_time' => now(),
            'txn_date' => now()->toDateString(),
        ]);

    mockQboEntryDisplayNames([
        'customer_name' => "Bill's Windsurf Shop",
        'project_name' => 'Test ABC',
        'item_name' => 'Design',
    ]);

    $result = app(TimeEntryListService::class)->listForUser($employee, $token, 1, 25);

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['list_id'])->toBe('qbo:88')
        ->and($result['data'][0]['customer_name'])->toBe("Bill's Windsurf Shop")
        ->and($result['data'][0]['project_name'])->toBe('Test ABC')
        ->and($result['data'][0]['item_name'])->toBe('Design')
        ->and($result['data'][0]['status'])->toBe('approved');
});

it('excludes quickbooks snapshots already linked to approved local entries', function () {
    $admin = User::factory()->admin()->create();
    $token = QuickBooksToken::factory()->forUser($admin)->create(['realm_id' => 'realm-dedupe']);
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
        'qbo_employee_ref' => '7',
    ]);

    seedListedTimeActivities($token, '7', 1, 99);
    TimeEntry::factory()->forUser($employee)->approved('99')->create([
        'description' => 'Already linked',
    ]);

    $result = app(TimeEntryListService::class)->listForUser($employee, $token, 1, 25);

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['list_id'])->toStartWith('local:');
});

it('clamps invalid pagination arguments before listing entries', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    TimeEntry::factory()->forUser($employee)->create();

    $result = app(TimeEntryListService::class)->listForUser($employee, null, 0, 0);

    expect($result['meta']['start_position'])->toBe(1)
        ->and($result['meta']['max_results'])->toBe(1);
});

it('marks list responses as truncated when more rows exist than the page size', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);

    foreach (range(1, 3) as $index) {
        TimeEntry::factory()->forUser($employee)->create([
            'start_time' => now()->subHours($index),
            'end_time' => now()->subHours($index - 1),
        ]);
    }

    $result = app(TimeEntryListService::class)->listForUser($employee, null, 1, 2);

    expect($result['data'])->toHaveCount(2)
        ->and($result['meta']['truncated'])->toBeTrue()
        ->and($result['meta']['count'])->toBe(2);
});

it('returns the requested page when start position is greater than one', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);

    TimeEntry::factory()->forUser($employee)->create([
        'description' => 'Newest',
        'start_time' => '2026-07-29 11:00:00',
        'end_time' => '2026-07-29 12:00:00',
    ]);
    TimeEntry::factory()->forUser($employee)->create([
        'description' => 'Older',
        'start_time' => '2026-07-28 11:00:00',
        'end_time' => '2026-07-28 12:00:00',
    ]);

    $result = app(TimeEntryListService::class)->listForUser($employee, null, 2, 1);

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['description'])->toBe('Older')
        ->and($result['meta']['start_position'])->toBe(2);
});

it('returns an empty page when no entries exist', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);

    $result = app(TimeEntryListService::class)->listForUser($employee, null, 1, 25);

    expect($result['data'])->toBe([])
        ->and($result['meta']['count'])->toBe(0)
        ->and($result['meta']['truncated'])->toBeFalse();
});
