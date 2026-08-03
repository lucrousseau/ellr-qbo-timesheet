<?php

use App\Models\Expense;
use App\Models\QuickBooksToken;
use App\Models\User;
use App\Services\ExpenseListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

covers(ExpenseListService::class);

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow('2026-08-03 12:00:00');
    mockQboExpensePickers();
});

afterEach(function () {
    Carbon::setTestNow();
});

it('lists local expenses for an employee', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    Expense::factory()->forUser($employee)->create([
        'description' => 'Local only',
        'txn_date' => '2026-08-02',
    ]);

    $result = app(ExpenseListService::class)->listForUser($employee, 1, 25);

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['description'])->toBe('Local only')
        ->and($result['meta']['truncated'])->toBeFalse();
});

it('clamps invalid pagination arguments before listing expenses', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);
    Expense::factory()->forUser($employee)->create();

    $result = app(ExpenseListService::class)->listForUser($employee, 0, 0);

    expect($result['meta']['start_position'])->toBe(1)
        ->and($result['meta']['max_results'])->toBe(1);
});

it('marks list responses as truncated when more rows exist than the page size', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);

    foreach (range(1, 3) as $index) {
        Expense::factory()->forUser($employee)->create([
            'txn_date' => now()->subDays($index)->toDateString(),
        ]);
    }

    $result = app(ExpenseListService::class)->listForUser($employee, 1, 2);

    expect($result['data'])->toHaveCount(2)
        ->and($result['meta']['truncated'])->toBeTrue()
        ->and($result['meta']['count'])->toBe(2);
});

it('returns the requested page when start position is greater than one', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);

    Expense::factory()->forUser($employee)->create([
        'description' => 'Newest',
        'txn_date' => '2026-08-03',
    ]);
    Expense::factory()->forUser($employee)->create([
        'description' => 'Older',
        'txn_date' => '2026-08-02',
    ]);

    $result = app(ExpenseListService::class)->listForUser($employee, 2, 1);

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['description'])->toBe('Older')
        ->and($result['meta']['start_position'])->toBe(2);
});

it('returns an empty page when no expenses exist', function () {
    $employee = User::factory()->create(['qbo_employee_ref' => '7']);

    $result = app(ExpenseListService::class)->listForUser($employee, 1, 25);

    expect($result['data'])->toBe([])
        ->and($result['meta']['count'])->toBe(0)
        ->and($result['meta']['truncated'])->toBeFalse();
});

it('lists only expenses owned by the signed in employee', function () {
    $admin = User::factory()->admin()->create();
    QuickBooksToken::factory()->forUser($admin)->create();
    $employee = User::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);
    $otherEmployee = User::factory()->create([
        'organization_id' => $admin->organization_id,
    ]);
    Expense::factory()->forUser($employee)->create(['description' => 'Mine']);
    Expense::factory()->forUser($otherEmployee)->create(['description' => 'Theirs']);

    $result = app(ExpenseListService::class)->listForUser($employee, 1, 25);

    expect($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['description'])->toBe('Mine');
});
